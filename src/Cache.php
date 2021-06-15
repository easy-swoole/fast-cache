<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 16:05
 */

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Exception;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use EasySwoole\Component\Singleton;
use EasySwoole\FastCache\Protocol\Package;
use EasySwoole\FastCache\Protocol\Protocol;
use EasySwoole\FastCache\Protocol\UnixClient;
use EasySwoole\FastCache\Server\Worker;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use swoole_server;

class Cache
{
    use Singleton;

    private $config;

    /** @var bool $hashAttachServer */
    private $hashAttachServer = false;

    function __construct(?Config $config = null)
    {
        if($config == null){
            $config = new Config();
        }
        $this->config = $config;
    }

    function getConfig():Config
    {
        return $this->config;
    }


    /**
     * 设置缓存
     * @param string $key 缓存key
     * @param string $value 需要缓存的内容(可序列化的内容都可缓存)
     * @param null $ttl 缓存有效时间
     * @param float $timeout socket等待超时 下同
     * @return bool|mixed|null
     */
    function set($key, $value, ?int $ttl = null, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_SET);
        $com->setValue($value);
        $com->setKey($key);
        $com->setOption($com::ACTION_TTL, $ttl);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 获取缓存
     * @param string $key 缓存key
     * @param float $timeout
     * @return mixed|null
     */
    function get($key, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_GET);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 删除一个key
     * @param string $key
     * @param float $timeout
     * @return bool|mixed|null
     */
    function unset($key, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_UNSET);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 获取当前全部的key
     * @param null $key
     * @param float $timeout
     * @return array|null
     */
    function keys($key = null, ?float $timeout = null): ?array
    {
        $com = new Package();
        $com->setCommand($com::ACTION_KEYS);
        $com->setKey($key);
        $info = $this->broadcast($com, $timeout);
        if (is_array($info)) {
            $ret = [];
            foreach ($info as $item) {
                if (is_array($item)) {
                    foreach ($item as $sub) {
                        $ret[] = $sub;
                    }
                }
            }
            return $ret;
        } else {
            return null;
        }
    }

    /**
     * 清空所有进程的数据
     * @param float $timeout
     * @return bool
     */
    function flush(?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_FLUSH);
        $this->broadcast($com, $timeout);
        return true;
    }

    /**
     * 推入队列
     * @param $key
     * @param $value
     * @param float $timeout
     * @return bool|mixed|null
     */
    public function enQueue($key, $value, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_ENQUEUE);
        $com->setValue($value);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 从队列中取出
     * @param $key
     * @param float $timeout
     * @return mixed|null
     */
    public function deQueue($key, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_DEQUEUE);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 队列当前长度
     * @param $key
     * @param float $timeout
     * @return mixed|null
     */
    public function queueSize($key, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_QUEUE_SIZE);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 释放队列
     * @param $key
     * @param float $timeout
     * @return bool|null
     */
    public function unsetQueue($key, $timeout = 1.0): ?bool
    {
        $com = new Package();
        $com->setCommand($com::ACTION_UNSET_QUEUE);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 返回当前队列的全部key名称
     * @param float $timeout
     * @return array|null
     */
    public function queueList($timeout = 1.0): ?array
    {
        $com = new Package();
        $com->setCommand($com::ACTION_QUEUE_LIST);
        $info = $this->broadcast($com, $timeout);
        if (is_array($info)) {
            $ret = [];
            foreach ($info as $item) {
                if (is_array($item)) {
                    foreach ($item as $sub) {
                        $ret[] = $sub;
                    }
                }
            }
            return $ret;
        } else {
            return null;
        }
    }

    /**
     * 清空所有队列
     * @param float $timeout
     * @return bool
     */
    function flushQueue(?float $timeout = null): bool
    {
        $com = new Package();
        $com->setCommand($com::ACTION_FLUSH_QUEUE);
        $this->broadcast($com, $timeout);
        return true;
    }

    /**
     * 设置一个key的过期时间
     * @param $key
     * @param int $ttl
     * @param float $timeout
     * @return mixed|null
     */
    function expire($key, int $ttl, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_EXPIRE);
        $com->setKey($key);
        $com->setValue($ttl);
        $com->setOption($com::ACTION_TTL, $ttl);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 移除一个key的过期时间
     * @param $key
     * @param float $timeout
     * @return mixed|null
     */
    function persist($key, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_PERSISTS);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 查看某个key的ttl
     * @param $key
     * @param float $timeout
     * @return mixed|null
     */
    function ttl($key, $timeout = 1.0)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_TTL);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 投递消息任务
     * @param Job $job
     * @param float $timeout
     * @return int|null
     */
    public function putJob(Job $job,?float $timeout = null):?int
    {
        $com = new Package();
        $com->setCommand($com::ACTION_PUT_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    public function getJob(string $jobQueue, ?float $timeout = null):?Job
    {
        $com = new Package();
        $com->setCommand($com::ACTION_GET_JOB);
        $com->setValue($jobQueue);
        return $this->sendAndRecv($this->generateSocket($jobQueue), $com, $timeout);
    }

    /**
     * 从延迟执行队列中拿取
     * @param string $queueName
     * @param float $timeout
     * @return Job|null
     */
    public function getDelayJob(string $queueName, ?float $timeout = null):?Job
    {
        $com = new Package();
        $com->setCommand($com::ACTION_GET_DELAY_JOB);
        $com->setValue($queueName);
        return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
    }

    /**
     * 从保留队列中拿取
     * @param string $queueName
     * @param float $timeout
     * @return Job|null
     */
    public function getReserveJob(string $queueName, ?float $timeout = null):?Job
    {
        $com = new Package();
        $com->setCommand($com::ACTION_GET_RESERVE_JOB);
        $com->setValue($queueName);
        return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
    }
    /**
     * 通过jobId将ready任务转为delay任务
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function delayJob(Job $job,?float $timeout = null):?bool
    {
        $com = new Package();
        $com->setCommand($com::ACTION_DELAY_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    /**
     * 任务重发
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function releaseJob(Job $job,?float $timeout = null):?bool
    {
        $com = new Package();
        $com->setCommand($com::ACTION_RELEASE_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    /**
     * 将ready 任务转为reserve任务
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function reserveJob(Job $job,?float $timeout = null):?bool
    {
        $com = new Package();
        $com->setCommand($com::ACTION_RESERVE_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    /**
     * 删除任务
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function deleteJob(Job $job,?float $timeout = null):?bool
    {
        if (!$job->getJobId()){
            return false;
        }
        if (!$job->getQueue()){
            return false;
        }

        $com = new Package();
        $com->setCommand($com::ACTION_DELETE_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    /**
     * 将某个任务bury掉 直到kick
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function buryJob(Job $job,?float $timeout = null):?bool
    {
        // 必须传递queueName和jobId
        if (empty($job->getJobId())){
            return false;
        }
        if (empty($job->getQueue())){
            return false;
        }

        $com = new Package();
        $com->setCommand($com::ACTION_BURY_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    /**
     * 从bury状态中拿取一个任务
     * @param string $queueName
     * @param float $timeout
     * @return Job|null
     */
    public function getBuryJob(string $queueName, ?float $timeout = null):?Job
    {
        $com = new Package();
        $com->setCommand($com::ACTION_GET_BURY_JOB);
        $com->setValue($queueName);
        return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
    }

    /**
     * 将bury任务恢复到ready中
     * @param Job $job
     * @param float $timeout
     * @return bool|null
     */
    public function kickJob(Job $job, ?float $timeout = null):?bool
    {
        // 必须传递queueName和jobId
        if (empty($job->getJobId())){
            return false;
        }
        if (empty($job->getQueue())){
            return false;
        }

        $com = new Package();
        $com->setCommand($com::ACTION_KICK_JOB);
        $com->setValue($job);
        return $this->sendAndRecv($this->generateSocket($job->getQueue()), $com, $timeout);
    }

    public function jobQueues(?float $timeout = null):?array
    {
        $com = new Package();
        $com->setCommand($com::ACTION_JOB_QUEUES);
        $info = $this->broadcast($com, $timeout);

        if (is_array($info)) {
            $ret = [];
            foreach ($info as $item) {
                if (is_array($item)) {
                    foreach ($item as $subKey => $sub) {
                        $ret[] = $sub;
                    }
                }
            }
            return $ret;
        } else {
            return null;
        }

    }

    public function flushJobQueue(string $jobQueue = null,?float $timeout = null)
    {
        if ($jobQueue !== null){
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_JOB);
            $com->setValue($jobQueue);
            return $this->sendAndRecv($this->generateSocket($jobQueue), $com, $timeout);
        }else{
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_JOB);
            $com->setValue($jobQueue);
            $info = $this->broadcast($com, $timeout);

            if (is_array($info)) {
                return $info;
            } else {
                return null;
            }
        }

    }

    /**
     * 只清空ready任务队列 可指定
     * @param string|NULL $queueName
     * @param float $timeout
     * @return array|mixed|null
     */
    public function flushReadyJobQueue(string $queueName = null,?float $timeout = null)
    {
        if ($queueName !== null){
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_READY_JOB);
            $com->setValue($queueName);
            return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
        }else{
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_READY_JOB);
            $com->setValue($queueName);
            $info = $this->broadcast($com, $timeout);

            if (is_array($info)) {
                return $info;
            } else {
                return null;
            }
        }
    }
    /**
     * 只清空reserve任务队列 可指定
     * @param string|NULL $queueName
     * @param float $timeout
     * @return array|mixed|null
     */
    public function flushReserveJobQueue(string $queueName = null,?float $timeout = null)
    {
        if ($queueName !== null){
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_RESERVE_JOB);
            $com->setValue($queueName);
            return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
        }else{
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_RESERVE_JOB);
            $com->setValue($queueName);
            $info = $this->broadcast($com, $timeout);

            if (is_array($info)) {
                return $info;
            } else {
                return null;
            }
        }
    }
    /**
     * 只清空BURY任务队列 可指定
     * @param string|NULL $queueName
     * @param float $timeout
     * @return array|mixed|null
     */
    public function flushBuryJobQueue(string $queueName = null,?float $timeout = null)
    {
        if ($queueName !== null){
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_BURY_JOB);
            $com->setValue($queueName);
            return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
        }else{
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_BURY_JOB);
            $com->setValue($queueName);
            $info = $this->broadcast($com, $timeout);

            if (is_array($info)) {
                return $info;
            } else {
                return null;
            }
        }
    }
    /**
     * 只清空delay任务队列 可指定
     * @param string|NULL $queueName
     * @param float $timeout
     * @return array|mixed|null
     */
    public function flushDelayJobQueue(string $queueName = null,?float $timeout = null)
    {
        if ($queueName !== null){
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_DELAY_JOB);
            $com->setValue($queueName);
            return $this->sendAndRecv($this->generateSocket($queueName), $com, $timeout);
        }else{
            $com = new Package();
            $com->setCommand($com::ACTION_FLUSH_DELAY_JOB);
            $com->setValue($queueName);
            $info = $this->broadcast($com, $timeout);

            if (is_array($info)) {
                return $info;
            } else {
                return null;
            }
        }
    }

    public function jobQueueSize(string $jobQueue,?float $timeout = null):?array
    {
        $com = new Package();
        $com->setCommand($com::ACTION_JOB_QUEUE_SIZE);
        $com->setValue($jobQueue);
        return $this->sendAndRecv($this->generateSocket($jobQueue), $com, $timeout);
    }

    function hset($key, $field, $value, ?int $ttl = null, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HSET);
        $com->setValue($value);
        $com->setField($field);
        $com->setKey($key);
        $com->setOption($com::ACTION_TTL, $ttl);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hget($key, $field=null, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HGET);
        $com->setKey($key);
        $com->setField($field);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hdel($key, $field=null, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HDEL);
        $com->setKey($key);
        $com->setField($field);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hflush(?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HFLUSH);
        $this->broadcast($com, $timeout);
        return true;
    }

    function hkeys($key, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HKEYS);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hscan($key, $cursor=0, $limit=10, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HSCAN);
        $com->setKey($key);
        $com->setCursor($cursor);
        $com->setLimit($limit);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hsetnx($key, $field, $value, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HSETNX);
        $com->setKey($key);
        $com->setField($field);
        $com->setValue($value);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hExists($key, $field, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HEXISTS);
        $com->setKey($key);
        $com->setField($field);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hLen($key, ?float $timeout = null) {
        $com = new Package();
        $com->setCommand($com::ACTION_HLEN);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hIncrby($key, $field, $value, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HINCRBY);
        $com->setKey($key);
        $com->setField($field);
        $com->setValue($value);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hMset($key, $fieldValues, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HMSET);
        $com->setKey($key);
        $com->setFieldValues($fieldValues);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hMget($key, $fields, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HMGET);
        $com->setKey($key);
        $com->setFields($fields);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hVals($key, ?float $timeout = null)
    {
        $com = new Package();
        $com->setCommand($com::ACTION_HVALS);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    function hGetAll($key, ?float $timeout = null) {
        $com = new Package();
        $com->setCommand($com::ACTION_HGETALL);
        $com->setKey($key);
        return $this->sendAndRecv($this->generateSocket($key), $com, $timeout);
    }

    /**
     * 绑定到当前主服务
     * @param swoole_server $server
     * @throws Exception
     */
    function attachToServer(swoole_server $server)
    {
        $list = $this->__initProcess();
        foreach ($list as $process) {
            $server->addProcess($process->getProcess());
        }
    }

    function __debug(Package $package,$workerIndex)
    {
        return $this->sendAndRecv($this->generateSocketByIndex($workerIndex), $package);
    }

    function __getWorkerIndex(string $key):int
    {
        return (base_convert(substr(md5($key), 0, 2), 16, 10) % $this->config->getWorkerNum());
    }

    /**
     * 初始化缓存进程
     * @return array
     * @throws Exception
     */
    public function __initProcess(): array
    {
        $this->hashAttachServer = true;
        $array = [];
        for ($i = 0; $i < $this->config->getWorkerNum(); $i++) {

            $config = new UnixProcessConfig();
            $config->setProcessName("{$this->config->getServerName()}.FastCacheProcess.{$i}");
            $config->setSocketFile($this->generateSocketByIndex($i));
            $config->setProcessGroup("{$this->config->getServerName()}.FastCacheProcess");
            $config->setAsyncCallback(false);
            $config->setArg($this->config);

            $array[$i] = new Worker($config);
        }
        return $array;
    }

    /**
     * 根据操作的KEY指定Socket管道
     * @param $key
     * @return string
     */
    private function generateSocket($key): string
    {
        return $this->generateSocketByIndex($this->__getWorkerIndex($key));
    }

    /**
     * 获取管道的文件名
     * @param $index
     * @return string
     */
    private function generateSocketByIndex($index)
    {
        return $this->config->getTempDir() . "/{$this->config->getServerName()}.FastCacheProcess.{$index}.sock";
    }

    /**
     * 发送并等待返回
     * @param $socketFile
     * @param Package $package
     * @param $timeout
     * @return mixed|null
     */
    private function sendAndRecv($socketFile, Package $package,?float $timeout = null)
    {
        if($timeout === null){
            $timeout = $this->config->getTimeout();
        }
        $maxPack = $this->config->getMaxPackageSize();
        $client = new UnixClient($socketFile,$maxPack,$timeout);
        $client->send(Protocol::pack(serialize($package)));
        $ret = $client->recv($timeout);
        if (!empty($ret)) {
            $ret = unserialize(Protocol::unpack($ret));
            if ($ret instanceof Package) {
                return $ret->getValue();
            }else {
                return $ret;
            }
        }
        return null;
    }

    /**
     * 进程广播
     * @param Package $command
     * @param float $timeout
     * @return array|mixed
     */
    private function broadcast(Package $command, $timeout = null)
    {
        if($timeout === null){
            $timeout = $this->config->getTimeout();
        }
        $info = [];
        $channel = new Channel($this->config->getWorkerNum() + 1);
        for ($i = 0; $i < $this->config->getWorkerNum(); $i++) {
            Coroutine::create(function () use ($command, $channel, $i, $timeout) {
                $ret = $this->sendAndRecv($this->generateSocketByIndex($i), $command, $timeout);
                $channel->push([
                    $i => $ret
                ]);
            });
        }
        $start = microtime(true);
        while (1) {
            if (microtime(true) - $start > $timeout) {
                break;
            }
            $temp = $channel->pop($timeout);
            if (is_array($temp)) {
                $info += $temp;
                if (count($info) == $this->config->getWorkerNum()) {
                    break;
                }
            }
        }
        return $info;
    }

}
