<?php


namespace EasySwoole\FastCache;


use EasySwoole\Spl\SplArray;

class SyncData
{
    protected $array;
    protected $queueArray = [];
    protected $ttlKeys = [];
    // queue支持
    protected $jobIds = [];
    protected $readyJob = [];
    protected $delayJob = [];
    protected $reserveJob = [];
    protected $buryJob = [];
    protected $hashMap = [];

    /**
     * @return mixed
     */
    public function getArray():SplArray
    {
        return $this->array;
    }

    /**
     * @param mixed $array
     */
    public function setArray(SplArray $array): void
    {
        $this->array = $array;
    }

    /**
     * @return mixed
     */
    public function getQueueArray()
    {
        return $this->queueArray;
    }

    /**
     * @param mixed $queueArray
     */
    public function setQueueArray(array $queueArray): void
    {
        $this->queueArray = $queueArray;
    }

    /**
     * @return array
     */
    public function getTtlKeys(): array
    {
        return $this->ttlKeys;
    }

    /**
     * @param array $ttlKeys
     */
    public function setTtlKeys(array $ttlKeys): void
    {
        $this->ttlKeys = $ttlKeys;
    }

    /**
     * @return array
     */
    public function getJobIds(): array
    {
        return $this->jobIds;
    }

    /**
     * @param array $jobIds
     */
    public function setJobIds(array $jobIds): void
    {
        $this->jobIds = $jobIds;
    }

    /**
     * @return array
     */
    public function getReadyJob(): array
    {
        return $this->readyJob;
    }

    /**
     * @param array $readyJob
     */
    public function setReadyJob(array $readyJob): void
    {
        $this->readyJob = $readyJob;
    }

    /**
     * @return array
     */
    public function getDelayJob(): array
    {
        return $this->delayJob;
    }

    /**
     * @param array $delayJob
     */
    public function setDelayJob(array $delayJob): void
    {
        $this->delayJob = $delayJob;
    }

    /**
     * @return array
     */
    public function getReserveJob(): array
    {
        return $this->reserveJob;
    }

    /**
     * @param array $reserveJob
     */
    public function setReserveJob(array $reserveJob): void
    {
        $this->reserveJob = $reserveJob;
    }

    /**
     * @return array
     */
    public function getBuryJob(): array
    {
        return $this->buryJob;
    }

    /**
     * @param array $buryJob
     */
    public function setBuryJob(array $buryJob): void
    {
        $this->buryJob = $buryJob;
    }

    /**
     * @param array $hashMap
     */
    public function setHashMap(array $hashMap): void
    {
        $this->hashMap = $hashMap;
    }

    /**
     * @return array
     */
    public function getHashMap(): array
    {
        return $this->hashMap;
    }

}