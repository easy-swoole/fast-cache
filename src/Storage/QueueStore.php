<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Storage;

use Swoole\Coroutine\Channel;

/** @internal */
final class QueueStore
{
    /** @var array<string, Channel> */
    private array $queues = [];

    public function has(string $key): bool
    {
        return isset($this->queues[$key]);
    }

    public function delQueue(string $key): bool
    {
        $queue = $this->queues[$key] ?? null;
        if ($queue === null) {
            return false;
        }

        unset($this->queues[$key]);
        $queue->close();

        return true;
    }

    public function closeAll(): void
    {
        foreach ($this->queues as $queue) {
            $queue->close();
        }
    }

    public function initQueue(string $key, int $capacity): bool
    {
        if (isset($this->queues[$key])) {
            $this->queues[$key]->close();
            $this->queues[$key] = new Channel($capacity);
        } else {
            $this->queue($key, $capacity);
        }

        return true;
    }

    public function push(string $key, mixed $value, float $timeout): bool
    {
        $queue = $this->queue($key);
        if ($timeout <= 0 && $queue->isFull()) {
            return false;
        }

        return $queue->push([$value], $timeout > 0 ? $timeout : -1);
    }

    public function pop(string $key, float $timeout): mixed
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
