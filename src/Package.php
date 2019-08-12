<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 16:32
 */

namespace EasySwoole\FastCache;


class Package
{
    protected $command;
    protected $value;
    protected $key;
    protected $options = [];

    const ACTION_SET = 11;
    const ACTION_GET =  12;
    const ACTION_KEYS = 13;
    const ACTION_UNSET = 14;
    const ACTION_PERSISTS = 15;
    const ACTION_EXPIRE = 16;
    const ACTION_TTL = 17;

    const ACTION_DEQUEUE  = 21;
    const ACTION_ENQUEUE  = 22;
    const ACTION_UNSET_QUEUE = 23;
    const ACTION_FLUSH_QUEUE = 24;
    const ACTION_QUEUE_LIST = 25;
    const ACTION_QUEUE_SIZE = 26;

    const ACTION_PUT_JOB = 30;
    const ACTION_GET_JOB = 31;
    const ACTION_DELAY_JOB = 32;
    const ACTION_GET_DELAY_JOB = 321;
    const ACTION_RELEASE_JOB = 33;
    const ACTION_RESERVE_JOB = 34;
    const ACTION_GET_RESERVE_JOB = 341;
    const ACTION_DELETE_JOB = 35;
    const ACTION_BURY_JOB = 36;
    const ACTION_GET_BURY_JOB = 361;
    const ACTION_KICK_JOB = 362;

    const ACTION_JOB_QUEUES = 37;
    const ACTION_FLUSH_JOB = 38;
    const ACTION_FLUSH_READY_JOB = 381;
    const ACTION_FLUSH_RESERVE_JOB = 382;
    const ACTION_FLUSH_BURY_JOB = 383;
    const ACTION_FLUSH_DELAY_JOB = 384;
    const ACTION_JOB_QUEUE_SIZE = 39;

    const ACTION_FLUSH = -1;


    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param mixed $command
     */
    public function setCommand($command): void
    {
        $this->command = $command;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $key
     */
    public function setKey($key): void
    {
        $this->key = $key;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getOption(string $name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}