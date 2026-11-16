<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Protocol;

use Swoole\Coroutine\Socket;

/** @internal */
final class PacketReader
{
    public static function read(Socket $socket, float $endLine): ?string
    {
        $header = self::readBytes($socket, PacketCodec::HEADER_SIZE, $endLine);
        if ($header === null) {
            return null;
        }

        $size = unpack('Nsize', $header)['size'];

        return $size > 0 ? self::readBytes($socket, $size, $endLine) : null;
    }

    /** @return array{body: string, endLine: float}|null */
    public static function readRequest(Socket $socket, float $headerEndLine): ?array
    {
        $header = self::readBytes($socket, PacketCodec::REQUEST_HEADER_SIZE, $headerEndLine);
        if ($header === null) {
            return null;
        }

        ['size' => $size, 'endLine' => $endLine] = unpack('Nsize/EendLine', $header);
        if ($size <= 0 || !is_finite($endLine) || $endLine <= 0) {
            return null;
        }

        $body = self::readBytes($socket, $size, $endLine);

        return $body === null ? null : ['body' => $body, 'endLine' => $endLine];
    }

    private static function readBytes(Socket $socket, int $size, float $endLine): ?string
    {
        // Grow the buffer as bytes arrive, independently of the announced size.
        $body = '';
        while (strlen($body) < $size) {
            $remaining = $endLine - hrtime(true) / 1_000_000_000;
            if ($remaining <= 0) {
                return null;
            }

            $length = min($size - strlen($body), 256 * 1024);
            $chunk = $socket->recvAll($length, $remaining);
            if ($chunk === false || strlen($chunk) !== $length) {
                return null;
            }

            $body .= $chunk;
        }

        return $body;
    }
}
