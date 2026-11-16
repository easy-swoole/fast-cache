<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Client;

use EasySwoole\FastCache\Protocol\PacketReader;
use RuntimeException;
use Swoole\Coroutine\Client;

/** @internal */
final class UnixClient
{
    public function __construct(private string $socketPath)
    {
    }

    public function exchange(string $packet, float $endLine): string
    {
        $client = new Client(SWOOLE_UNIX_STREAM);

        if (!$client->connect($this->socketPath, 0, $this->remaining($endLine))) {
            throw new RuntimeException("Could not connect to FastCache (error {$client->errCode})");
        }

        try {
            $socket = $client->exportSocket();
            $sent = $socket->sendAll($packet, $this->remaining($endLine));
            if ($sent !== strlen($packet)) {
                throw new RuntimeException("Could not send FastCache request (error {$socket->errCode})");
            }

            $response = PacketReader::read($socket, $endLine);
            if ($response === null) {
                throw new RuntimeException("Could not receive FastCache response (error {$socket->errCode})");
            }

            return $response;
        } finally {
            if ($client->isConnected()) {
                $client->close();
            }
        }
    }

    private function remaining(float $endLine): float
    {
        $remaining = $endLine - hrtime(true) / 1_000_000_000;
        if ($remaining <= 0) {
            throw new RuntimeException('FastCache request timed out');
        }

        return $remaining;
    }
}
