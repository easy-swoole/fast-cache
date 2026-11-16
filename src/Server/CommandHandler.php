<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Server;

use EasySwoole\FastCache\Protocol\Command;
use EasySwoole\FastCache\Protocol\OpCode;
use EasySwoole\FastCache\Storage\HashStore;
use EasySwoole\FastCache\Storage\QueueStore;
use EasySwoole\FastCache\Storage\ValueStore;
use RuntimeException;

/** @internal */
final class CommandHandler
{
    private const QUEUE_RESPONSE_MARGIN = 0.1;

    public function __construct(
        private ValueStore $values,
        private HashStore $hashes,
        private QueueStore $queues,
    ) {
    }

    public function dispatch(Command $command): mixed
    {
        $operation = $command->op;
        $data = $command->data;
        $key = $command->key;
        $validData = match ($operation) {
            OpCode::GET, OpCode::DEL, OpCode::POP, OpCode::HAS_INIT_QUEUE, OpCode::DEL_QUEUE => $data === null,
            OpCode::PUSH => true,
            OpCode::SET => is_array($data) && array_is_list($data) && count($data) === 2
                && ($data[1] === null || (is_int($data[1]) && $data[1] > 0)),
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

        $remaining = $command->endLine - hrtime(true) / 1_000_000_000;
        if ($remaining <= 0) {
            throw new RuntimeException('FastCache request timed out');
        }

        $queueTimeout = $remaining - self::QUEUE_RESPONSE_MARGIN;

        return match ($operation) {
            OpCode::SET => $this->values->set($key, $data[0], $data[1]),
            OpCode::GET => $this->values->get($key),
            OpCode::DEL => $this->values->del($key),
            OpCode::HSET => $this->hashes->hset($key, $data[0], $data[1]),
            OpCode::HGET => $this->hashes->hget($key, $data),
            OpCode::HDEL => $this->hashes->hdel($key, $data),
            OpCode::HSCAN => $this->hashes->hscan($key, $data[0], $data[1]),
            OpCode::INIT_QUEUE => $this->queues->initQueue($key, $data),
            OpCode::DEL_QUEUE => $this->queues->delQueue($key),
            OpCode::HAS_INIT_QUEUE => $this->queues->has($key),
            OpCode::PUSH => $this->queues->push($key, $data, $queueTimeout),
            OpCode::POP => $this->queues->pop($key, $queueTimeout),
        };
    }
}
