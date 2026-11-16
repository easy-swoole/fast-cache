<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Server;

use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use EasySwoole\FastCache\Protocol\Command;
use EasySwoole\FastCache\Protocol\PacketCodec;
use EasySwoole\FastCache\Protocol\PacketReader;
use EasySwoole\FastCache\Storage\HashStore;
use EasySwoole\FastCache\Storage\QueueStore;
use EasySwoole\FastCache\Storage\ValueStore;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;

/** @internal */
final class CacheProcess extends AbstractUnixProcess
{
    private ValueStore $values;
    private QueueStore $queues;
    private CommandHandler $handler;

    public function __construct(
        UnixProcessConfig $config,
        private float $timeout,
        private string $maxMemLimit,
    ) {
        parent::__construct($config);
        $this->values = new ValueStore();
        $this->queues = new QueueStore();
        $this->handler = new CommandHandler($this->values, new HashStore(), $this->queues);
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
            if (Coroutine::create($this->runExpirationSweep(...)) === false) {
                throw new RuntimeException('Could not start FastCache expiration coroutine');
            }
            parent::run($arg);
        } finally {
            $this->isShutDown = true;
            umask($mask);
            if (isset($this->socketServer) && file_exists($socketPath) && filetype($socketPath) === 'socket') {
                unlink($socketPath);
            }
        }
    }

    protected function onShutDown(): void
    {
        $this->isShutDown = true;
        if (isset($this->socketServer)) {
            parent::onShutDown();
        }
        $this->queues->closeAll();
    }

    public function onAccept(Socket $socket): void
    {
        try {
            $request = PacketReader::readRequest($socket, hrtime(true) / 1_000_000_000 + $this->timeout);
            if ($request === null) {
                return;
            }

            $endLine = $request['endLine'];
            try {
                $message = PacketCodec::decode($request['body']);
                $message['endLine'] = $endLine;
                $command = Command::fromArray($message);
                $response = PacketCodec::encode([true, $this->handler->dispatch($command)]);
            } catch (\Throwable $exception) {
                $response = PacketCodec::encode([false, $exception->getMessage()]);
            }

            $sendTimeout = $endLine - hrtime(true) / 1_000_000_000;
            if ($sendTimeout > 0) {
                $socket->sendAll($response, $sendTimeout);
            }
        } finally {
            $socket->close();
        }
    }

    private function runExpirationSweep(): void
    {
        while (!$this->isShutDown) {
            $count = $this->values->purgeExpired(256);
            Coroutine::sleep($count === 256 ? 0.001 : 0.1);
        }
    }
}
