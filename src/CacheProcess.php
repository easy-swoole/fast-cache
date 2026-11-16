<?php

declare(strict_types=1);

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use RuntimeException;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Socket;

/** @internal */
final class CacheProcess extends AbstractUnixProcess
{
    private array $values = [];
    private array $hashes = [];
    /** @var array<string, Channel> */
    private array $queues = [];

    public function __construct(
        UnixProcessConfig $config,
        private float $timeout,
    ) {
        parent::__construct($config);
    }

    public function run($arg): void
    {
        $socketPath = $this->getConfig()->getSocketFile();
        if (file_exists($socketPath) || is_link($socketPath)) {
            throw new RuntimeException("FastCache socket path already exists: {$socketPath}");
        }

        $mask = umask(0077);
        try {
            parent::run($arg);
        } finally {
            umask($mask);
            if (isset($this->socketServer) && file_exists($socketPath) && filetype($socketPath) === 'socket') {
                unlink($socketPath);
            }
        }
    }

    protected function onShutDown(): void
    {
        if (isset($this->socketServer)) {
            parent::onShutDown();
        }
        foreach ($this->queues as $queue) {
            $queue->close();
        }
    }

    public function onAccept(Socket $socket): void
    {
        try {
            $header = $socket->recvAll(4, $this->timeout);
            if ($header === false || strlen($header) !== 4) {
                return;
            }

            $size = unpack('Nsize', $header)['size'];
            if ($size === 0 || $size + 4 > Protocol::MAX_PACKET_SIZE) {
                return;
            }

            $body = $socket->recvAll($size, $this->timeout);
            if ($body === false || strlen($body) !== $size) {
                return;
            }

            try {
                $command = Command::fromArray(Protocol::decode($body));
                $response = Protocol::encode([true, $this->dispatch($command)]);
            } catch (\Throwable $exception) {
                $response = Protocol::encode([false, $exception->getMessage()]);
            }

            $socket->sendAll($response, $this->timeout);
        } finally {
            $socket->close();
        }
    }

    private function dispatch(Command $command): mixed
    {
        $operation = $command->op;
        $data = $command->data;
        $key = $command->key;
        $validData = match ($operation) {
            OpCode::GET, OpCode::DEL, OpCode::POP, OpCode::HAS_INIT_QUEUE => $data === null,
            OpCode::SET, OpCode::PUSH => true,
            OpCode::HGET => is_string($data),
            OpCode::INIT_QUEUE => is_int($data) && $data > 0,
            OpCode::HSET => is_array($data) && array_is_list($data) && count($data) === 2
                && is_string($data[0]),
        };
        if (!$validData) {
            throw new RuntimeException('Invalid FastCache command data');
        }

        $remaining = $command->endLine - Protocol::now();
        $queueTimeout = $remaining + Protocol::QUEUE_TIMEOUT_GRACE;
        if ($operation === OpCode::PUSH || $operation === OpCode::POP) {
            if ($queueTimeout <= 0) {
                return $operation === OpCode::PUSH ? false : null;
            }
        } elseif ($remaining <= 0) {
            throw new RuntimeException('FastCache request timed out');
        }

        return match ($operation) {
            OpCode::SET => $this->set($key, $data),
            OpCode::GET => $this->get($key),
            OpCode::DEL => $this->del($key),
            OpCode::HSET => $this->hset($key, $data[0], $data[1]),
            OpCode::HGET => $this->hget($key, $data),
            OpCode::INIT_QUEUE => $this->initQueue($key, $data),
            OpCode::HAS_INIT_QUEUE => isset($this->queues[$key]),
            OpCode::PUSH => $this->queue($key)->push([$data], $queueTimeout),
            OpCode::POP => $this->pop($key, $queueTimeout),
        };
    }

    private function set(string $key, mixed $value): bool
    {
        $this->del($key);
        $this->values[$key] = $value;

        return true;
    }

    private function get(string $key): mixed
    {
        if (isset($this->queues[$key])) {
            throw new RuntimeException('FastCache key contains a queue');
        }

        return $this->hashes[$key] ?? $this->values[$key] ?? null;
    }

    private function del(string $key): bool
    {
        $exists = array_key_exists($key, $this->values) || isset($this->hashes[$key])
            || isset($this->queues[$key]);

        $queue = $this->queues[$key] ?? null;
        unset($this->values[$key], $this->hashes[$key], $this->queues[$key]);
        $queue?->close();

        return $exists;
    }

    private function hset(string $key, string $field, mixed $value): bool
    {
        if (array_key_exists($key, $this->values) || isset($this->queues[$key])) {
            throw new RuntimeException('FastCache key does not contain a hash');
        }

        $this->hashes[$key][$field] = $value;

        return true;
    }

    private function hget(string $key, string $field): mixed
    {
        return $this->hashes[$key][$field] ?? null;
    }

    private function initQueue(string $key, int $capacity): bool
    {
        if (isset($this->queues[$key])) {
            $this->queues[$key]->close();
            $this->queues[$key] = new Channel($capacity);
        } else {
            $this->queue($key, $capacity);
        }

        return true;
    }

    private function pop(string $key, float $timeout): mixed
    {
        $item = $this->queue($key)->pop($timeout);

        return $item === false ? null : $item[0];
    }

    private function queue(string $key, int $capacity = PHP_INT_MAX): Channel
    {
        if (array_key_exists($key, $this->values) || isset($this->hashes[$key])) {
            throw new RuntimeException('FastCache key does not contain a queue');
        }

        return $this->queues[$key] ??= new Channel($capacity);
    }
}
