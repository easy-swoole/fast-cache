<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 16:19
 */

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Socket\UnixProcessConfig;

class CacheProcessConfig extends UnixProcessConfig
{
    protected $tempDir;
    protected $onTick;
    protected $tickInterval = 5 * 1000;
    protected $onStart;
    protected $onShutdown;
    protected $backlog;
    protected $workerIndex;
    protected $maxMem = '512M';
    protected $queueReserveTime = 60;
    protected $queueMaxReleaseTimes = 10;

    /**
     * @return mixed
     */
    public function getTempDir()
    {
        return $this->tempDir;
    }

    /**
     * @param mixed $tempDir
     */
    public function setTempDir($tempDir): void
    {
        $this->tempDir = $tempDir;
    }

    /**
     * @return mixed
     */
    public function getProcessName()
    {
        return $this->processName;
    }

    /**
     * @param mixed $processName
     */
    public function setProcessName($processName): void
    {
        $this->processName = $processName;
    }

    /**
     * @return mixed
     */
    public function getOnTick()
    {
        return $this->onTick;
    }

    /**
     * @param mixed $onTick
     */
    public function setOnTick($onTick): void
    {
        $this->onTick = $onTick;
    }

    /**
     * @return float|int
     */
    public function getTickInterval()
    {
        return $this->tickInterval;
    }

    /**
     * @param float|int $tickInterval
     */
    public function setTickInterval($tickInterval): void
    {
        $this->tickInterval = $tickInterval;
    }

    /**
     * @return mixed
     */
    public function getOnStart()
    {
        return $this->onStart;
    }

    /**
     * @param mixed $onStart
     */
    public function setOnStart($onStart): void
    {
        $this->onStart = $onStart;
    }

    /**
     * @return mixed
     */
    public function getOnShutdown()
    {
        return $this->onShutdown;
    }

    /**
     * @param mixed $onShutdown
     */
    public function setOnShutdown($onShutdown): void
    {
        $this->onShutdown = $onShutdown;
    }

    /**
     * @return int
     */
    public function getBacklog(): int
    {
        return $this->backlog;
    }

    /**
     * @param int $backlog
     */
    public function setBacklog(int $backlog): void
    {
        $this->backlog = $backlog;
    }

    /**
     * @return mixed
     */
    public function getWorkerIndex()
    {
        return $this->workerIndex;
    }

    /**
     * @param mixed $workerIndex
     */
    public function setWorkerIndex($workerIndex): void
    {
        $this->workerIndex = $workerIndex;
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