<?php


namespace EasySwoole\FastCache;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $tempDir;
    protected $serverName = 'EasySwoole';
    protected $workerNum = 3;
    protected $timeout = 3.0;
    protected $maxPackageSize = 1024 * 1024 * 2;
    protected $maxMem = '512M';
    protected $jobReserveTime = 60;
    protected $jobMaxReleaseTimes = 3;

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
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @param string $serverName
     */
    public function setServerName(string $serverName): void
    {
        $this->serverName = $serverName;
    }

    /**
     * @return int
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum;
    }

    /**
     * @param int $workerNum
     */
    public function setWorkerNum(int $workerNum): void
    {
        $this->workerNum = $workerNum;
    }


    /**
     * @return float
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @param float $timeout
     */
    public function setTimeout(float $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return float|int
     */
    public function getMaxPackageSize()
    {
        return $this->maxPackageSize;
    }

    /**
     * @param float|int $maxPackageSize
     */
    public function setMaxPackageSize($maxPackageSize): void
    {
        $this->maxPackageSize = $maxPackageSize;
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

    protected function initialize(): void
    {
        if (empty($this->tempDir)) {
            $this->tempDir = getcwd();
        }
    }
}