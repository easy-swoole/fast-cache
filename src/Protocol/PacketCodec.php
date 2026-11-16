<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Protocol;

use RuntimeException;

/** @internal */
final class PacketCodec
{
    public const HEADER_SIZE = 4;
    public const REQUEST_HEADER_SIZE = self::HEADER_SIZE + 8;

    public static function encodeRequest(Command $command): string
    {
        $message = $command->toArray();
        unset($message['endLine']);

        return self::frame($message, pack('E', $command->endLine));
    }

    public static function encode(array $message): string
    {
        return self::frame($message, '');
    }

    private static function frame(array $message, string $prefix): string
    {
        $body = serialize($message);
        $size = strlen($body);

        if ($size > 0xffffffff) {
            throw new RuntimeException('FastCache body length exceeds the 32-bit packet header');
        }

        return pack('N', $size) . $prefix . $body;
    }

    public static function decode(string $body): array
    {
        $message = unserialize($body);
        if (!is_array($message)) {
            throw new RuntimeException('Invalid FastCache packet');
        }

        return $message;
    }
}
