<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Protocol;

use RuntimeException;

/** @internal */
final class Command
{
    public function __construct(
        public readonly OpCode $op,
        public readonly string $key,
        public readonly float $endLine,
        public readonly mixed $data = null,
    ) {
        if (!is_finite($endLine) || $endLine <= 0) {
            throw new RuntimeException('FastCache command endLine must be finite and positive');
        }
    }

    public static function fromArray(array $message): self
    {
        $endLine = $message['endLine'] ?? null;
        if (count($message) !== 4 || !is_string($message['op'] ?? null)
            || !is_string($message['key'] ?? null) || !array_key_exists('data', $message)
            || !is_float($endLine)
        ) {
            throw new RuntimeException('Invalid FastCache command');
        }

        $op = OpCode::tryFrom($message['op']);
        if ($op === null) {
            throw new RuntimeException('Unknown FastCache operation');
        }

        return new self($op, $message['key'], $endLine, $message['data']);
    }

    public function toArray(): array
    {
        return [
            'op' => $this->op->value,
            'key' => $this->key,
            'data' => $this->data,
            'endLine' => $this->endLine,
        ];
    }
}
