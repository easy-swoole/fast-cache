<?php

declare(strict_types=1);

namespace EasySwoole\FastCache;

use RuntimeException;

/** @internal */
final class Protocol
{
    public const QUEUE_TIMEOUT_GRACE = 0.1;

    public const MAX_PACKET_SIZE = 8 * 1024 * 1024;

    public static function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    public static function encode(array $message): string
    {
        $body = serialize($message);
        $size = strlen($body);

        if ($size + 4 > self::MAX_PACKET_SIZE) {
            throw new RuntimeException('FastCache packet exceeds 8 MiB');
        }

        return pack('N', $size) . $body;
    }

    public static function decode(string $body): array
    {
        $message = unserialize($body, ['allowed_classes' => false]);
        if (!is_array($message)) {
            throw new RuntimeException('Invalid FastCache packet');
        }

        return $message;
    }
}
