<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/5
 * Time: 8:15 PM
 */

namespace EasySwoole\FastCache;


use EasySwoole\FastCache\Storage\MMap;

class Config
{
    private $compressLevel;
    private $keySlotsMem;
    private $maxCacheMem;
    private $cacheSlotsMem;
    private $max_queue_mem;
    private $storageInterface = MMap::class;
    private $cacheDir;

    /**
     * @return mixed
     */
    public function getCompressLevel()
    {
        return $this->compressLevel;
    }

    /**
     * @param mixed $compressLevel
     */
    public function setCompressLevel($compressLevel): void
    {
        $this->compressLevel = $compressLevel;
    }

    /**
     * @return mixed
     */
    public function getKeySlotsMem()
    {
        return $this->keySlotsMem;
    }

    /**
     * @param mixed $keySlotsMem
     */
    public function setKeySlotsMem($keySlotsMem): void
    {
        $this->keySlotsMem = $keySlotsMem;
    }

    /**
     * @return mixed
     */
    public function getMaxCacheMem()
    {
        return $this->maxCacheMem;
    }

    /**
     * @param mixed $maxCacheMem
     */
    public function setMaxCacheMem($maxCacheMem): void
    {
        $this->maxCacheMem = $maxCacheMem;
    }

    /**
     * @return mixed
     */
    public function getCacheSlotsMem()
    {
        return $this->cacheSlotsMem;
    }

    /**
     * @param mixed $cacheSlotsMem
     */
    public function setCacheSlotsMem($cacheSlotsMem): void
    {
        $this->cacheSlotsMem = $cacheSlotsMem;
    }

    /**
     * @return mixed
     */
    public function getMaxQueueMem()
    {
        return $this->max_queue_mem;
    }

    /**
     * @param mixed $max_queue_mem
     */
    public function setMaxQueueMem($max_queue_mem): void
    {
        $this->max_queue_mem = $max_queue_mem;
    }

    /**
     * @return string
     */
    public function getStorageInterface(): string
    {
        return $this->storageInterface;
    }

    /**
     * @param string $storageInterface
     */
    public function setStorageInterface(string $storageInterface): void
    {
        $this->storageInterface = $storageInterface;
    }

    /**
     * @return mixed
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @param mixed $cacheDir
     */
    public function setCacheDir($cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

}