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
    /** @var array<string, array{id: string, fields: list<string>, values: array}> */
    private array $hashes = [];
    /** @var array<string, Channel> */
    private array $queues = [];

    public function __construct(
        UnixProcessConfig $config,
        private float $timeout,
        private string $maxMemLimit,
    ) {
        parent::__construct($config);
    }

    public function run($arg): void
    {
        if (ini_set('memory_limit', $this->maxMemLimit) === false) {
            throw new RuntimeException("Could not set FastCache memory_limit to {$this->maxMemLimit}");
        }

        $socketPath = $this->getConfig()->getSocketFile();
        if (is_link($socketPath) || (file_exists($socketPath) && filetype($socketPath) !== 'socket')) {
            throw new RuntimeException("FastCache socket path is occupied by a non-socket file: {$socketPath}");
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

            $command = null;
            try {
                $command = Command::fromArray(Protocol::decode($body));
                $response = Protocol::encode([true, $this->dispatch($command)]);
            } catch (\Throwable $exception) {
                $response = Protocol::encode([false, $exception->getMessage()]);
            }

            $sendTimeout = $command === null
                ? $this->timeout
                : min($this->timeout, $command->endLine - Protocol::now());
            if ($sendTimeout > 0) {
                $socket->sendAll($response, $sendTimeout);
            }
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
            OpCode::HGET, OpCode::HDEL => is_string($data),
            OpCode::HSCAN => is_array($data) && array_is_list($data) && count($data) === 2
                && ($data[0] === null || is_string($data[0])) && is_int($data[1]) && $data[1] > 0,
            OpCode::INIT_QUEUE => is_int($data) && $data > 0,
            OpCode::HSET => is_array($data) && array_is_list($data) && count($data) === 2
                && is_string($data[0]),
        };
        if (!$validData) {
            throw new RuntimeException('Invalid FastCache command data');
        }

        $remaining = $command->endLine - Protocol::now();
        if ($remaining <= 0) {
            throw new RuntimeException('FastCache request timed out');
        }

        $queueTimeout = $remaining - Protocol::QUEUE_RESPONSE_MARGIN;

        return match ($operation) {
            OpCode::SET => $this->set($key, $data),
            OpCode::GET => $this->get($key),
            OpCode::DEL => $this->del($key),
            OpCode::HSET => $this->hset($key, $data[0], $data[1]),
            OpCode::HGET => $this->hget($key, $data),
            OpCode::HDEL => $this->hdel($key, $data),
            OpCode::HSCAN => $this->hscan($key, $data[0], $data[1]),
            OpCode::INIT_QUEUE => $this->initQueue($key, $data),
            OpCode::HAS_INIT_QUEUE => isset($this->queues[$key]),
            OpCode::PUSH => $this->push($key, $data, $queueTimeout),
            OpCode::POP => $this->pop($key, $queueTimeout),
        };
    }

    private function set(string $key, mixed $value): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    private function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
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
        if (!isset($this->hashes[$key])) {
            $this->hashes[$key] = ['id' => bin2hex(random_bytes(16)), 'fields' => [], 'values' => []];
        }

        $hash = &$this->hashes[$key];
        if (!array_key_exists($field, $hash['values'])) {
            $hash['fields'][] = $field;
        }
        $hash['values'][$field] = $value;

        return true;
    }

    private function hget(string $key, string $field): mixed
    {
        return $this->hashes[$key]['values'][$field] ?? null;
    }

    private function hdel(string $key, string $field): bool
    {
        if (!isset($this->hashes[$key]) || !array_key_exists($field, $this->hashes[$key]['values'])) {
            return false;
        }

        $hash = &$this->hashes[$key];
        if (count($hash['fields']) === 1) {
            unset($this->hashes[$key]);

            return true;
        }

        $id = bin2hex(random_bytes(16));
        $position = array_search($field, $hash['fields'], true);
        array_splice($hash['fields'], $position, 1);
        unset($hash['values'][$field]);
        $hash['id'] = $id;

        return true;
    }

    private function hscan(string $key, ?string $cursor, int $count): array
    {
        $hash = $this->hashes[$key] ?? null;
        $offset = 0;
        $fieldCount = count($hash['fields'] ?? []);
        if ($cursor !== null) {
            if ($hash === null || !preg_match('/\A([a-f0-9]{32}):([1-9][0-9]*)\z/', $cursor, $parts)
                || $parts[1] !== $hash['id'] || (string) (int) $parts[2] !== $parts[2]
                || (int) $parts[2] >= $fieldCount
            ) {
                throw new RuntimeException('Invalid or expired FastCache hscan cursor');
            }
            $offset = (int) $parts[2];
        }

        if ($hash === null) {
            return ['cursor' => null, 'items' => []];
        }

        $items = [];
        $itemsSize = 0;
        $nextCursor = null;
        $limit = min($count, $fieldCount - $offset);
        for ($index = 0; $index < $limit; $index++) {
            $position = $offset + $index;
            $field = $hash['fields'][$position];
            $item = ['field' => $field, 'value' => $hash['values'][$field]];
            $candidateSize = $itemsSize + strlen(serialize($index)) + strlen(serialize($item));
            $next = $position + 1 < $fieldCount ? $hash['id'] . ':' . ($position + 1) : null;
            $envelopeSize = Protocol::packetSize([true, ['cursor' => $next, 'items' => []]]);
            $countDigits = strlen((string) ($index + 1)) - 1;
            if ($candidateSize + $envelopeSize + $countDigits > Protocol::MAX_PACKET_SIZE) {
                break;
            }

            $items[] = $item;
            $itemsSize = $candidateSize;
            $nextCursor = $next;
        }

        // Reference IDs can grow when separately sized items are serialized together.
        while ($items !== []) {
            $page = ['cursor' => $nextCursor, 'items' => $items];
            if (Protocol::packetSize([true, $page]) <= Protocol::MAX_PACKET_SIZE) {
                return $page;
            }

            $items = array_slice($items, 0, intdiv(count($items), 2));
            $nextCursor = $hash['id'] . ':' . ($offset + count($items));
        }

        throw new RuntimeException('FastCache hscan field exceeds the 8 MiB page limit');
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

    private function push(string $key, mixed $value, float $timeout): bool
    {
        $queue = $this->queue($key);
        if ($timeout <= 0 && $queue->isFull()) {
            return false;
        }

        return $queue->push([$value], $timeout > 0 ? $timeout : -1);
    }

    private function pop(string $key, float $timeout): mixed
    {
        $queue = $this->queue($key);
        if ($timeout <= 0 && $queue->isEmpty()) {
            return null;
        }

        $item = $queue->pop($timeout > 0 ? $timeout : -1);

        return $item === false ? null : $item[0];
    }

    private function queue(string $key, int $capacity = PHP_INT_MAX): Channel
    {
        return $this->queues[$key] ??= new Channel($capacity);
    }
}
