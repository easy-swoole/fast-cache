<?php


namespace EasySwoole\FastCache\Server;


use EasySwoole\Component\Process\Socket\UnixProcessConfig;

class WorkerConfig extends UnixProcessConfig
{
    protected $backlog;
    protected $maxMem = '512M';
    protected $jobReserveTime = 60;
    protected $jobMaxReleaseTimes = 3;

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
    public function getJobReserveTime(): int
    {
        return $this->jobReserveTime;
    }

    /**
     * @param int $jobReserveTime
     */
    public function setJobReserveTime(int $jobReserveTime): void
    {
        $this->jobReserveTime = $jobReserveTime;
    }

    /**
     * @return int
     */
    public function getJobMaxReleaseTimes(): int
    {
        return $this->jobMaxReleaseTimes;
    }

    /**
     * @param int $jobMaxReleaseTimes
     */
    public function setJobMaxReleaseTimes(int $jobMaxReleaseTimes): void
    {
        $this->jobMaxReleaseTimes = $jobMaxReleaseTimes;
    }
}