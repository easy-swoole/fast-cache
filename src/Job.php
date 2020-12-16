<?php


namespace EasySwoole\FastCache;


use EasySwoole\Spl\SplBean;

class Job extends SplBean
{
    /**
     * @var string
     */
    protected $queue;
    protected $jobId;
    protected $data;
    protected $delay = 0;
    protected $nextDoTime = 0;
    protected $dequeueTime = 0;
    /** @var int 重发次数 用于限制任务重发最大限制 */
    protected $releaseTimes = 0;

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     */
    public function setQueue(string $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * @return mixed
     */
    public function getJobId()
    {
        return $this->jobId;
    }

    /**
     * @param mixed $jobId
     */
    public function setJobId($jobId): void
    {
        $this->jobId = $jobId;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * @param int $delay
     */
    public function setDelay(int $delay): void
    {
        $this->delay = $delay;
    }

    /**
     * @return int
     */
    public function getNextDoTime(): int
    {
        return $this->nextDoTime;
    }

    /**
     * @param int $nextDoTime
     */
    public function setNextDoTime(int $nextDoTime): void
    {
        $this->nextDoTime = $nextDoTime;
    }

    /**
     * @return int
     */
    public function getDequeueTime(): int
    {
        return $this->dequeueTime;
    }

    /**
     * @param int $dequeueTime
     */
    public function setDequeueTime(int $dequeueTime): void
    {
        $this->dequeueTime = $dequeueTime;
    }

    /**
     * @return int
     */
    public function getReleaseTimes(): int
    {
        return $this->releaseTimes;
    }

    /**
     * @param int $releaseTimes
     */
    public function setReleaseTimes(int $releaseTimes): void
    {
        $this->releaseTimes = $releaseTimes;
    }
}