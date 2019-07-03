<?php


namespace EasySwoole\FastCache;


use EasySwoole\Spl\SplArray;

class SyncData
{
    protected $array;
    protected $queueArray = [];

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
}