<?php

declare(strict_types=1);

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use InvalidArgumentException;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Client as CoroutineClient;
use Swoole\Process;
use Swoole\Server;

final class FastCache
{
    private string $socketPath;
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config === null ? new Config() : clone $config;
        $tempDir = $this->config->tempDir;
        $serverName = $this->config->serverName;

        if ($tempDir === null || !str_starts_with($tempDir, '/') || str_contains($tempDir, "\0")) {
            throw new InvalidArgumentException('FastCache tempDir must be an absolute path');
        }
        if (trim($serverName) === '' || str_contains($serverName, '/') || str_contains($serverName, "\0")) {
            throw new InvalidArgumentException('FastCache serverName must be a non-empty filename component');
        }
        if (!is_finite($this->config->timeout) || $this->config->timeout <= 0) {
            throw new InvalidArgumentException('FastCache timeout must be finite and positive');
        }

        $this->socketPath = rtrim($tempDir, '/') . "/{$serverName}.fastCache.sock";
    }

    protected function createProcess(): Process
    {
        $config = new UnixProcessConfig();
        $config->setProcessName($this->config->serverName . '-FastCache');
        $config->setSocketFile($this->socketPath);
        $config->setPipeType(UnixProcessConfig::PIPE_TYPE_NONE);

        return (new CacheProcess(
            $config,
            $this->config->timeout,
            $this->config->maxMemLimit,
        ))->getProcess();
    }

    public function attachToServer(Server $server): void
    {
        if ($server->addProcess($this->createProcess()) === false) {
            throw new RuntimeException('Could not attach the FastCache process');
        }
    }

    public function set(string $key, mixed $value, ?float $timeout = null): bool
    {
        return $this->request(OpCode::SET, $key, $timeout, $value);
    }

    public function get(string $key, ?float $timeout = null): mixed
    {
        return $this->request(OpCode::GET, $key, $timeout);
    }

    public function del(string $key, ?float $timeout = null): bool
    {
        return $this->request(OpCode::DEL, $key, $timeout);
    }

    public function hset(string $key, string $field, mixed $value, ?float $timeout = null): bool
    {
        return $this->request(OpCode::HSET, $key, $timeout, [$field, $value]);
    }

    public function hget(string $key, string $field, ?float $timeout = null): mixed
    {
        return $this->request(OpCode::HGET, $key, $timeout, $field);
    }

    public function hscan(
        string $key,
        ?string $cursor = null,
        int $count = 100,
        ?float $timeout = null,
    ): array {
        if ($count < 1) {
            throw new InvalidArgumentException('FastCache hscan count must be positive');
        }

        return $this->request(OpCode::HSCAN, $key, $timeout, [$cursor, $count]);
    }

    public function initQueue(string $queueName, int $queueCapacity = 1024 * 512, ?float $timeout = null): bool
    {
        if ($queueCapacity < 1) {
            throw new InvalidArgumentException('FastCache queue capacity must be positive');
        }

        return $this->request(OpCode::INIT_QUEUE, $queueName, $timeout, $queueCapacity);
    }

    public function hasInitQueue(string $queueName, ?float $timeout = null): bool
    {
        return $this->request(OpCode::HAS_INIT_QUEUE, $queueName, $timeout);
    }

    public function push(string $key, mixed $value, ?float $timeout = null): bool
    {
        return $this->request(OpCode::PUSH, $key, $timeout, $value);
    }

    public function pop(string $key, ?float $timeout = null): mixed
    {
        return $this->request(OpCode::POP, $key, $timeout);
    }

    private function request(OpCode $operation, string $key, ?float $timeout, mixed $data = null): mixed
    {
        $timeout ??= $this->config->timeout;
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException('FastCache timeout must be finite and positive');
        }

        $endLine = Protocol::now() + $timeout;

        $packet = Protocol::encode((new Command($operation, $key, $endLine, $data))->toArray());
        $response = Coroutine::getCid() >= 0
            ? $this->exchangeCoroutine($packet, $endLine)
            : $this->exchangeSync($packet, $endLine);

        $message = Protocol::decode($response);
        if (!array_is_list($message) || count($message) !== 2 || !is_bool($message[0])) {
            throw new RuntimeException('Invalid FastCache response');
        }

        [$success, $value] = $message;
        if (!$success) {
            throw new RuntimeException($value);
        }

        return $value;
    }

    private function exchangeCoroutine(string $packet, float $endLine): string
    {
        $client = new CoroutineClient(SWOOLE_UNIX_STREAM);
        $client->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => Protocol::MAX_PACKET_SIZE,
        ]);

        if (!$client->connect($this->socketPath, 0, $this->remaining($endLine))) {
            throw new RuntimeException("Could not connect to FastCache (error {$client->errCode})");
        }

        try {
            $sent = $client->exportSocket()->sendAll($packet, $this->remaining($endLine));
            if ($sent !== strlen($packet)) {
                throw new RuntimeException("Could not send FastCache request (error {$client->errCode})");
            }

            $response = $client->recv($this->remaining($endLine));
            if (!$response) {
                throw new RuntimeException("Could not receive FastCache response (error {$client->errCode})");
            }

            return substr($response, 4);
        } finally {
            if ($client->isConnected()) {
                $client->close();
            }
        }
    }

    private function exchangeSync(string $packet, float $endLine): string
    {
        $stream = stream_socket_client('unix://' . $this->socketPath, $errorCode, $errorMessage, $this->remaining($endLine));
        if ($stream === false) {
            throw new RuntimeException("Could not connect to FastCache (error {$errorCode}: {$errorMessage})");
        }

        try {
            for ($offset = 0, $length = strlen($packet); $offset < $length; $offset += $sent) {
                $this->setStreamTimeout($stream, $this->remaining($endLine));
                $sent = fwrite($stream, substr($packet, $offset));
                if (!$sent) {
                    throw new RuntimeException('Could not send FastCache request');
                }
            }

            $header = $this->readStream($stream, 4, $endLine);

            $size = unpack('Nsize', $header)['size'];
            if ($size === 0 || $size + 4 > Protocol::MAX_PACKET_SIZE) {
                throw new RuntimeException('Invalid FastCache response size');
            }

            return $this->readStream($stream, $size, $endLine);
        } finally {
            fclose($stream);
        }
    }

    private function remaining(float $endLine): float
    {
        $remaining = $endLine - Protocol::now();
        if ($remaining <= 0) {
            throw new RuntimeException('FastCache request timed out');
        }

        return $remaining;
    }

    /** @param resource $stream */
    private function readStream($stream, int $size, float $endLine): string
    {
        $data = '';
        while (strlen($data) < $size) {
            $this->setStreamTimeout($stream, $this->remaining($endLine));
            $chunk = fread($stream, $size - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Could not receive FastCache response');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /** @param resource $stream */
    private function setStreamTimeout($stream, float $timeout): void
    {
        $seconds = (int) min(floor($timeout), PHP_INT_MAX >> 1);
        $microseconds = (int) ceil(($timeout - floor($timeout)) * 1_000_000);
        stream_set_timeout($stream, $seconds, max($seconds === 0 ? 1 : 0, $microseconds));
    }
}
