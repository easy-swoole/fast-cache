<?php

declare(strict_types=1);

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use EasySwoole\FastCache\Client\UnixClient;
use EasySwoole\FastCache\Protocol\Command;
use EasySwoole\FastCache\Protocol\OpCode;
use EasySwoole\FastCache\Protocol\PacketCodec;
use EasySwoole\FastCache\Server\CacheProcess;
use InvalidArgumentException;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Server;

class FastCache
{
    private string $socketPath;
    private Config $config;
    private UnixClient $client;

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

        $this->validateMemoryLimit($this->config->maxMemLimit);

        $this->socketPath = rtrim($tempDir, '/') . "/{$serverName}.fastCache.sock";
        $this->client = new UnixClient($this->socketPath);
    }

    private function validateMemoryLimit(string $limit): void
    {
        if ($limit === '-1') {
            return;
        }

        if (preg_match('/\A([1-9][0-9]*)([KMG]?)\z/i', $limit, $parts)) {
            $multiplier = match (strtoupper($parts[2])) {
                'K' => 1024,
                'M' => 1024 * 1024,
                'G' => 1024 * 1024 * 1024,
                '' => 1,
            };

            if (filter_var($parts[1], FILTER_VALIDATE_INT, [
                'options' => ['max_range' => intdiv(PHP_INT_MAX, $multiplier)],
            ]) !== false) {
                return;
            }
        }

        throw new InvalidArgumentException(
            'FastCache maxMemLimit must be a positive byte count, a size such as 512M, or -1'
        );
    }

    private function createProcess(): Process
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

    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
        ?float $timeout = null,
    ): bool {
        if ($ttl !== null && $ttl <= 0) {
            throw new InvalidArgumentException('FastCache TTL must be a positive integer');
        }

        return $this->request(OpCode::SET, $key, $timeout, [$value, $ttl]);
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

    public function hdel(string $key, string $field, ?float $timeout = null): bool
    {
        return $this->request(OpCode::HDEL, $key, $timeout, $field);
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

    public function delQueue(string $queueName, ?float $timeout = null): bool
    {
        return $this->request(OpCode::DEL_QUEUE, $queueName, $timeout);
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
        if (Coroutine::getCid() < 0) {
            throw new RuntimeException('FastCache operations require a Swoole coroutine');
        }

        $timeout ??= $this->config->timeout;
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException('FastCache timeout must be finite and positive');
        }

        $endLine = hrtime(true) / 1_000_000_000 + $timeout;

        $packet = PacketCodec::encodeRequest(new Command($operation, $key, $endLine, $data));
        $response = $this->client->exchange($packet, $endLine);

        $message = PacketCodec::decode($response);
        if (!array_is_list($message) || count($message) !== 2 || !is_bool($message[0])) {
            throw new RuntimeException('Invalid FastCache response');
        }

        [$success, $value] = $message;
        if (!$success) {
            throw new RuntimeException($value);
        }

        return $value;
    }
}
