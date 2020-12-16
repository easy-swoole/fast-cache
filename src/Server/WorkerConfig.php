<?php


namespace EasySwoole\FastCache\Server;


use EasySwoole\Component\Process\Socket\UnixProcessConfig;

class WorkerConfig extends UnixProcessConfig
{
    protected $backlog;
    protected $maxMem = '512M';
    protected $queueReserveTime = 60;
    protected $queueMaxReleaseTimes = 10;

    /**
     * @return mixed
     */
    public function getBacklog()
    {
        return $this->backlog;
    }

    /**
     * @param mixed $backlog
     */
    public function setBacklog($backlog): void
    {
        $this->backlog = $backlog;
    }

    /**
     * @return string
     */
    public function getMaxMem(): string
    {
        return $this->maxMem;
    }

    /**
     * @param string $maxMem
     */
    public function setMaxMem(string $maxMem): void
    {
        $this->maxMem = $maxMem;
    }

    /**
     * @return int
     */
    public function getQueueReserveTime(): int
    {
        return $this->queueReserveTime;
    }

    /**
     * @param int $queueReserveTime
     */
    public function setQueueReserveTime(int $queueReserveTime): void
    {
        $this->queueReserveTime = $queueReserveTime;
    }

    /**
     * @return int
     */
    public function getQueueMaxReleaseTimes(): int
    {
        return $this->queueMaxReleaseTimes;
    }

    /**
     * @param int $queueMaxReleaseTimes
     */
    public function setQueueMaxReleaseTimes(int $queueMaxReleaseTimes): void
    {
        $this->queueMaxReleaseTimes = $queueMaxReleaseTimes;
    }
}