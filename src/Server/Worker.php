<?php


namespace EasySwoole\FastCache\Server;


use EasySwoole\Component\Process\Exception;
use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\FastCache\Config;
use EasySwoole\FastCache\Job;
use EasySwoole\FastCache\Protocol\Package;
use EasySwoole\FastCache\Protocol\Protocol;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;

class Worker extends AbstractUnixProcess
{

    /** @var Config $fastCacheConfig */
    protected $fastCacheConfig;

    /**
     * 数组存放当前的缓存内容
     * @var array
     */
    protected $dataArray = [];

    /**
     * 存放队列
     * @var array
     */
    protected $queueArray = [];

    /**
     * 带有过期时间的Key
     * @var array
     */
    protected $ttlKeys = [];

    /**
     * 分配的任务id
     * @var array
     */
    protected $jobIds = [];
    /**
     * 可以执行的任务
     * @var array
     */
    protected $readyJob = [];
    /**
     * 延迟执行的任务
     * @var array
     */
    protected $delayJob = [];
    /**
     * 保留任务（正在执行还未确认结果）
     * @var array
     */
    protected $reserveJob = [];
    /**
     * 埋藏状态的任务
     * @var array
     */
    protected $buryJob = [];

    /**
     * hash相关
     * @var array
     */
    protected $hashMap = [];

    /**
     * 进程初始化并开始监听Socket
     * @param $arg
     * @throws Exception
     */
    public function run($arg)
    {
        /** @var Config $fastCacheConfig */
        $fastCacheConfig = $this->fastCacheConfig = $arg;
        ini_set('memory_limit', $fastCacheConfig->getMaxMem());
        Coroutine::create(function () use ($fastCacheConfig) {
            while (1) {
                try {
                    if (!empty($this->ttlKeys)) {
                        foreach ($this->ttlKeys as $ttlKey => $expire) {
                            if ($expire < time()) {
                                unset($this->ttlKeys[$ttlKey], $this->dataArray[$ttlKey]);
                            }
                        }
                    }
                    // 检测消息队列可执行性
                    foreach ($this->delayJob as $queueName => $jobs) {
                        /** @var Job $job */
                        foreach ($jobs as $jobKey => $job) {
                            // 是否可以执行
                            if ($job->getNextDoTime() <= time()) {
                                $canDo = $this->delayJob[$queueName][$jobKey];
                                unset($this->delayJob[$queueName][$jobKey]);
                                $this->readyJob[$queueName]["_" . $job->getJobId()] = $canDo;
                            }
                        }
                    }
                    // 检测保留任务是否超时
                    foreach ($this->reserveJob as $queueName => $jobs) {
                        /** @var Job $job */
                        foreach ($jobs as $jobKey => $job) {
                            // 取出时间 + 超时时间 < 当前时间 则放回ready
                            if ($job->getDequeueTime() + $fastCacheConfig->getJobReserveTime() < time()) {
                                $readyJob = $this->reserveJob[$queueName][$jobKey];
                                unset($this->reserveJob[$queueName][$jobKey]);
                                // 判断最大重发次数
                                $releaseTimes = $job->getReleaseTimes();
                                if ($releaseTimes < $fastCacheConfig->getJobMaxReleaseTimes()) {
                                    $job->setReleaseTimes(++$releaseTimes);
                                    // 如果是延迟队列 更新nextDoTime
                                    if ($job->getDelay() > 0) {
                                        $job->setNextDoTime(time() + $job->getDelay());
                                    }
                                    $this->readyJob[$queueName]["_" . $job->getJobId()] = $readyJob;
                                }
                            }
                        }
                    }

                } catch (\Throwable $throwable) {
                    $this->onException($throwable);
                }
                Coroutine::sleep(0.49);
            }
        });
        parent::run($fastCacheConfig);
    }

    /**
     * UnixClientAccept
     * @param Socket $socket
     */
    public function onAccept(Socket $socket)
    {
        // 收取包头4字节计算包长度 收不到4字节包头丢弃该包
        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $socket->close();
            return;
        }
        // 收包头声明的包长度 包长一致进入命令处理流程
        $allLength = Protocol::packDataLength($header);
        $data = $socket->recvAll($allLength, 1);
        if (strlen($data) == $allLength) {
            $replyPackage = $this->executeCommand($data);
            $socket->sendAll(Protocol::pack(serialize($replyPackage)));
            $socket->close();
        }
        // 否则丢弃该包不进行处理
        $socket->close();
    }

    /**
     * 执行命令
     * @param $commandPayload
     * @return mixed
     */
    protected function executeCommand(?string $commandPayload)
    {
        $replayData = null;
        $fromPackage = unserialize($commandPayload);
        if ($fromPackage instanceof Package) {
            switch ($fromPackage->getCommand()) {
                case Package::ACTION_SET:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $value = $fromPackage->getValue();
                    // 按照redis的逻辑 当前key没有过期 set不会重置ttl 已过期则重新设置
                    $ttl = $fromPackage->getOption(Package::ACTION_TTL);
                    if (!array_key_exists($key, $this->ttlKeys) || $this->ttlKeys[$key] < time()) {
                        if (!is_null($ttl)) {
                            $this->ttlKeys[$key] = time() + $ttl;
                        }
                    }
                    $this->dataArray[$key] = $value;
                    break;
                }
                case Package::ACTION_GET:
                {
                    $key = $fromPackage->getKey();
                    // 取出之前需要先判断当前是否有ttl 如果有ttl设置并且已经过期 立刻删除key
                    if (array_key_exists($key, $this->ttlKeys) && $this->ttlKeys[$key] < time()) {
                        unset($this->ttlKeys[$key]);
                        unset($this->dataArray[$key]);
                        $replayData = null;
                    }
                    if (isset($this->dataArray[$fromPackage->getKey()])) {
                        $replayData = $this->dataArray[$fromPackage->getKey()];
                    }
                    break;
                }
                case Package::ACTION_UNSET:
                {
                    $replayData = true;
                    unset($this->ttlKeys[$fromPackage->getKey()]); // 同时移除TTL
                    unset($this->dataArray[$fromPackage->getKey()]);
                    break;
                }
                case Package::ACTION_KEYS:
                {
                    /** 检查一次过期数据 */
                    $time = time();
                    foreach ($this->ttlKeys as $ttlKey => $ttl) {
                        if ($ttl < $time) {
                            unset($this->dataArray[$ttlKey], $this->ttlKeys[$ttlKey]);
                        }
                    }
                    $keys = array_keys($this->dataArray);
                    $replayData = $keys;
                    break;
                }
                case Package::ACTION_FLUSH:
                {
                    $replayData = true;
                    $this->ttlKeys = [];
                    $this->dataArray = [];
                    $this->queueArray = [];
                    $this->hashMap = [];
                    $this->jobIds = [];
                    $this->buryJob = [];
                    $this->readyJob = [];
                    $this->delayJob = [];
                    $this->reserveJob = [];
                    break;
                }
                case Package::ACTION_EXPIRE:
                {
                    $replayData = false;
                    $key = $fromPackage->getKey();
                    $ttl = $fromPackage->getOption(Package::ACTION_TTL);
                    if (array_key_exists($key, $this->dataArray)) {
                        if (!is_null($ttl)) {
                            $this->ttlKeys[$key] = time() + $ttl;
                            $replayData = true;
                        }
                    }

                    break;
                }
                case Package::ACTION_PERSISTS:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    unset($this->ttlKeys[$key]);
                    break;
                }
                case Package::ACTION_TTL:
                {
                    $replayData = null;
                    $key = $fromPackage->getKey();
                    $time = time();

                    // 不能查询当前没有的Key
                    if (array_key_exists($key, $this->dataArray) && array_key_exists($key, $this->ttlKeys)) {
                        $expire = $this->ttlKeys[$key];
                        if ($expire > $time) {  // 有剩余时间时才会返回剩余ttl 否则返回null表示已经过期或未设置 不区分主动过期和key不存在的情况
                            $replayData = $expire - $time;
                        }
                    }
                    break;
                }
                case Package::ACTION_ENQUEUE:
                {
                    $que = $this->initQueue($fromPackage->getKey());
                    $data = $fromPackage->getValue();
                    if ($data !== null) {
                        $que->enqueue($fromPackage->getValue());
                        $replayData = true;
                    } else {
                        $replayData = false;
                    }
                    break;
                }
                case Package::ACTION_DEQUEUE:
                {
                    $que = $this->initQueue($fromPackage->getKey());
                    if ($que->isEmpty()) {
                        $replayData = null;
                    } else {
                        $replayData = $que->dequeue();
                    }
                    break;
                }
                case Package::ACTION_QUEUE_SIZE:
                {
                    $que = $this->initQueue($fromPackage->getKey());
                    $replayData = $que->count();
                    break;
                }
                case Package::ACTION_UNSET_QUEUE:
                {
                    if (isset($this->queueArray[$fromPackage->getKey()])) {
                        unset($this->queueArray[$fromPackage->getKey()]);
                        $replayData = true;
                    } else {
                        $replayData = false;
                    }
                    break;
                }
                case Package::ACTION_QUEUE_LIST:
                {
                    $replayData = array_keys($this->queueArray);
                    break;
                }
                case Package::ACTION_FLUSH_QUEUE:
                {
                    $this->queueArray = [];
                    $replayData = true;
                    break;
                }
                case Package::ACTION_PUT_JOB:
                {
                    // 设置jobId 储存
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = $this->getJobId($queueName);

                    $job->setJobId($jobId);

                    $jobKey = "_" . $jobId;
                    // 判断是否为延迟队列
                    if ($job->getDelay() > 0) {
                        $job->setNextDoTime(time() + $job->getDelay());
                        $this->delayJob[$queueName][$jobKey] = $job;
                    } else {
                        $this->readyJob[$queueName][$jobKey] = $job;
                    }

                    $replayData = $jobId;
                    break;
                }
                case Package::ACTION_GET_JOB:
                {
                    $queueName = $fromPackage->getValue();
                    if (!empty($this->readyJob[$queueName])) {
                        /** @var Job $job */
                        $job = array_shift($this->readyJob[$queueName]);
                        // 设置reserveTime 放到reserveJob队列
                        $job->setDequeueTime(time());
                        $jobId = "_" . $job->getJobId();
                        $this->reserveJob[$queueName][$jobId] = $job;
                    } else {
                        $job = null;
                    }
                    $replayData = $job;
                    break;
                }

                case Package::ACTION_DELAY_JOB:
                {
                    $replayData = false;
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = "_" . $job->getJobId();

                    $delay = $job->getDelay();
                    if ($delay == 0) {
                        break;
                    }

                    $originJob = null;
                    $originJob = $originJob ?? (isset($this->readyJob[$queueName][$jobId]) ? $this->readyJob[$queueName][$jobId] : null);
                    $originJob = $originJob ?? (isset($this->reserveJob[$queueName][$jobId]) ? $this->reserveJob[$queueName][$jobId] : null);
                    $originJob = $originJob ?? (isset($this->buryJob[$queueName][$jobId]) ? $this->buryJob[$queueName][$jobId] : null);

                    if (!$originJob) {
                        $replayData = false;
                        break;
                    }
                    $originJob->setDelay($delay);
                    $originJob->setNextDoTime(time() + $delay);
                    $this->delayJob[$queueName][$jobId] = $originJob;
                    unset($this->readyJob[$queueName][$jobId]);
                    unset($this->reserveJob[$queueName][$jobId]);
                    unset($this->buryJob[$queueName][$jobId]);
                    $replayData = true;
                    break;
                }
                case Package::ACTION_GET_DELAY_JOB:
                {
                    /** @var Job $job */
                    $queueName = $fromPackage->getValue();

                    if (isset($this->delayJob[$queueName])) {
                        $job = array_shift($this->delayJob[$queueName]);
                    } else {
                        $job = null;
                    }
                    $replayData = $job;
                    break;
                }
                case Package::ACTION_GET_RESERVE_JOB:
                {
                    // 从保留任务中拿取
                    /** @var Job $job */
                    $queueName = $fromPackage->getValue();
                    if (isset($this->reserveJob[$queueName])) {
                        $job = array_shift($this->reserveJob[$queueName]);
                    } else {
                        $job = null;
                    }
                    $replayData = $job;
                    break;
                }
                case Package::ACTION_DELETE_JOB:
                {
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $jobId = "_" . $job->getJobId();
                    $queueName = $job->getQueue();
                    if (isset($this->readyJob[$queueName][$jobId])) {
                        unset($this->readyJob[$queueName][$jobId]);
                        $replayData = true;
                        break;
                    }
                    if (isset($this->reserveJob[$queueName][$jobId])) {
                        unset($this->reserveJob[$queueName][$jobId]);
                        $replayData = true;
                        break;
                    }
                    if (isset($this->delayJob[$queueName][$jobId])) {
                        unset($this->delayJob[$queueName][$jobId]);
                        $replayData = true;
                        break;
                    }
                    if (isset($this->buryJob[$queueName][$jobId])) {
                        unset($this->buryJob[$queueName][$jobId]);
                        $replayData = true;
                        break;
                    }
                    $replayData = false;
                    break;
                }
                case Package::ACTION_JOB_QUEUES:
                {
                    $readyJob = array_keys($this->readyJob);
                    $delayJob = array_keys($this->delayJob);
                    $reserveJob = array_keys($this->reserveJob);
                    $buryJob = array_keys($this->buryJob);

                    $queue = array_unique(array_merge($readyJob, $delayJob, $reserveJob, $buryJob));
                    $replayData = $queue;
                    break;
                }
                case Package::ACTION_JOB_QUEUE_SIZE:
                {
                    $queueName = $fromPackage->getValue();
                    $return = [
                        'ready' => isset($this->readyJob[$queueName]) ? count($this->readyJob[$queueName]) : 0,
                        'delay' => isset($this->delayJob[$queueName]) ? count($this->delayJob[$queueName]) : 0,
                        'reserve' => isset($this->reserveJob[$queueName]) ? count($this->reserveJob[$queueName]) : 0,
                        'bury' => isset($this->buryJob[$queueName]) ? count($this->buryJob[$queueName]) : 0,
                    ];
                    $replayData = $return;
                    break;
                }
                case Package::ACTION_FLUSH_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if ($queueName === null) {
                        $this->readyJob = [];
                        $this->delayJob = [];
                        $this->reserveJob = [];
                        $this->buryJob = [];
                    } else {
                        unset($this->readyJob[$queueName]);
                        unset($this->delayJob[$queueName]);
                        unset($this->reserveJob[$queueName]);
                        unset($this->buryJob[$queueName]);
                    }

                    $replayData = true;
                    break;
                }
                case Package::ACTION_FLUSH_READY_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if ($queueName === null) {
                        $this->readyJob = [];
                    } else {
                        unset($this->readyJob[$queueName]);
                    }

                    $replayData = true;
                    break;
                }
                case Package::ACTION_FLUSH_RESERVE_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if ($queueName === null) {
                        $this->reserveJob = [];
                    } else {
                        unset($this->reserveJob[$queueName]);
                    }

                    $replayData = true;
                    break;
                }
                case Package::ACTION_FLUSH_BURY_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if ($queueName === null) {
                        $this->buryJob = [];
                    } else {
                        unset($this->buryJob[$queueName]);
                    }

                    $replayData = true;
                    break;
                }
                case Package::ACTION_FLUSH_DELAY_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if ($queueName === null) {
                        $this->delayJob = [];
                    } else {
                        unset($this->delayJob[$queueName]);
                    }

                    $replayData = true;
                    break;
                }
                case Package::ACTION_RELEASE_JOB:
                {
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = $job->getJobId();
                    $jobKey = "_" . $jobId;
                    $delay = $job->getDelay();

                    // $job需要重新取 兼容手动提供queueNam和jobId来重发任务

                    $originJob = null;
                    $originJob = $originJob ?? (isset($this->readyJob[$queueName][$jobKey]) ? $this->readyJob[$queueName][$jobKey] : null);
                    $originJob = $originJob ?? (isset($this->delayJob[$queueName][$jobKey]) ? $this->delayJob[$queueName][$jobKey] : null);
                    $originJob = $originJob ?? (isset($this->reserveJob[$queueName][$jobKey]) ? $this->reserveJob[$queueName][$jobKey] : null);
                    $originJob = $originJob ?? (isset($this->buryJob[$queueName][$jobKey]) ? $this->buryJob[$queueName][$jobKey] : null);

                    // 没有该任务
                    if (!$originJob) {
                        $replayData = false;
                        break;
                    }
                    $originJob->setDelay($delay);

                    unset($this->readyJob[$queueName][$jobKey]);
                    unset($this->delayJob[$queueName][$jobKey]);
                    unset($this->reserveJob[$queueName][$jobKey]);
                    unset($this->buryJob[$queueName][$jobKey]);

                    // 是否达到最大重发次数
                    $fastCacheConfig = $this->fastCacheConfig;
                    if ($originJob->getReleaseTimes() > $fastCacheConfig->getJobMaxReleaseTimes()) {
                        $replayData = false;
                        break;
                    }

                    $releaseTimes = $originJob->getReleaseTimes();
                    $originJob->setReleaseTimes(++$releaseTimes);

                    // 判断是否为延迟队列
                    if ($originJob->getDelay() > 0) {
                        $originJob->setNextDoTime(time() + $originJob->getDelay());
                        $this->delayJob[$queueName][$jobKey] = $originJob;
                    } else {
                        $this->readyJob[$queueName][$jobKey] = $originJob;
                    }

                    $replayData = $jobId;
                    break;
                }
                case Package::ACTION_RESERVE_JOB:
                {
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = "_" . $job->getJobId();

                    $originJob = null;
                    $originJob = $originJob ?? (isset($this->readyJob[$queueName][$jobId]) ? $this->readyJob[$queueName][$jobId] : null);
                    $originJob = $originJob ?? (isset($this->delayJob[$queueName][$jobId]) ? $this->delayJob[$queueName][$jobId] : null);
                    $originJob = $originJob ?? (isset($this->buryJob[$queueName][$jobId]) ? $this->buryJob[$queueName][$jobId] : null);

                    if (!$originJob) {
                        $replayData = false;
                        break;
                    }

                    $originJob->setDequeueTime(time());

                    $this->reserveJob[$queueName][$jobId] = $originJob;

                    unset($this->readyJob[$queueName][$jobId]);
                    unset($this->delayJob[$queueName][$jobId]);
                    unset($this->buryJob[$queueName][$jobId]);

                    $replayData = true;
                    break;
                }
                case Package::ACTION_BURY_JOB:
                {
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = $job->getJobId();
                    $jobKey = "_" . $jobId;

                    // 重新拿job 兼容手动传递jobId来bury
                    $originJob = $originJob ?? (isset($this->readyJob[$queueName][$jobKey]) ? $this->readyJob[$queueName][$jobKey] : null);
                    $originJob = $originJob ?? (isset($this->delayJob[$queueName][$jobKey]) ? $this->delayJob[$queueName][$jobKey] : null);
                    $originJob = $originJob ?? (isset($this->reserveJob[$queueName][$jobKey]) ? $this->reserveJob[$queueName][$jobKey] : null);

                    // 没有该任务
                    if (!$originJob) {
                        $replayData = false;
                        break;
                    }


                    $this->buryJob[$queueName][$jobKey] = $originJob;

                    unset($this->readyJob[$queueName][$jobKey]);
                    unset($this->delayJob[$queueName][$jobKey]);
                    unset($this->reserveJob[$queueName][$jobKey]);

                    $replayData = true;
                    break;
                }
                case Package::ACTION_GET_BURY_JOB:
                {
                    $queueName = $fromPackage->getValue();

                    if (isset($this->buryJob[$queueName])) {
                        $job = array_shift($this->buryJob[$queueName]);
                    } else {
                        $job = null;
                    }

                    $replayData = $job;
                    break;
                }
                case Package::ACTION_KICK_JOB:
                {
                    /** @var Job $job */
                    $job = $fromPackage->getValue();
                    $queueName = $job->getQueue();
                    $jobId = $job->getJobId();
                    $jobKey = "_" . $jobId;

                    if (isset($this->buryJob[$queueName][$jobKey])) {
                        $readyJob = $this->buryJob[$queueName][$jobKey];
                        unset($this->buryJob[$queueName][$jobKey]);
                        $this->readyJob[$queueName][$jobKey] = $readyJob;
                        $replayData = true;
                    } else {
                        $replayData = false;
                    }
                    break;

                }
                case Package::ACTION_HSET:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    $value = $fromPackage->getValue();
                    if (empty($field) || empty($value)) {
                        $replayData = false;
                    } else {
                        $this->hashMap[$key][$field] = $value;
                    }
                    break;
                }
                case Package::ACTION_HGET:
                {
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    if (empty($key)) {
                        $replayData = null;
                    } elseif (empty($field)) {
                        $replayData = $this->hashMap[$key];
                    } else {
                        $replayData = $this->hashMap[$key][$field];
                    }
                    break;
                }
                case Package::ACTION_HDEL:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    if (empty($key)) {
                        $replayData = false;
                    } else if (empty($field)) {
                        unset($this->hashMap[$key]);
                    } else {
                        unset($this->hashMap[$key][$field]);
                    }
                    break;
                }
                case Package::ACTION_HFLUSH:
                {
                    $this->hashMap = [];
                    break;
                }
                case Package::ACTION_HKEYS:
                {
                    $replayData = null;
                    $key = $fromPackage->getKey();
                    if (!empty($this->hashMap[$key])) {
                        $replayData = array_keys($this->hashMap[$key]);
                    }
                    break;
                }
                case Package::ACTION_HSCAN:
                {
                    $replayData = null;
                    $key = $fromPackage->getKey();
                    $limit = $fromPackage->getLimit();
                    $cursor = $fromPackage->getCursor();
                    if (!empty($this->hashMap[$key])) {
                        $replayData = array_slice($this->hashMap[$key], $cursor, $limit);
                        if (count($replayData) < $limit) {
                            $replayData = [
                                'data' => $replayData,
                                'cursor' => 0
                            ];
                        } else {
                            $replayData = [
                                'data' => $replayData,
                                'cursor' => $cursor + $limit
                            ];
                        }
                    }
                    break;
                }
                case Package::ACTION_HSETNX:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    $value = $fromPackage->getValue();
                    if (empty($this->hashMap[$key]) || empty($this->hashMap[$key][$value])) {
                        $this->hashMap[$key][$field] = $value;
                    }
                    break;
                }
                case Package::ACTION_HEXISTS:
                {
                    $replayData = false;
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    if (isset($this->hashMap[$key][$field])) {
                        $replayData = true;
                    }
                    break;
                }
                case Package::ACTION_HLEN:
                {
                    $replayData = 0;
                    $key = $fromPackage->getKey();
                    if (isset($this->hashMap[$key])) {
                        $replayData = count($this->hashMap[$key]);
                    }
                    break;
                }
                case Package::ACTION_HINCRBY:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $field = $fromPackage->getField();
                    $value = $fromPackage->getValue();

                    if (isset($this->hashMap[$key][$field])) {
                        if (is_numeric($this->hashMap[$key][$field])) {
                            $this->hashMap[$key][$field] += $value;
                        } else {
                            $replayData = false;
                        }
                    } else {
                        $this->hashMap[$key][$field] = $value;
                    }
                    break;
                }
                case Package::ACTION_HMSET:
                {
                    $replayData = true;
                    $key = $fromPackage->getKey();
                    $fieldValues = $fromPackage->getFieldValues();
                    foreach ($fieldValues as $field => $value) {
                        $this->hashMap[$key][$field] = $value;
                    }
                    break;
                }
                case Package::ACTION_HMGET:
                {
                    $replayData = [];
                    $key = $fromPackage->getKey();
                    $fields = $fromPackage->getFields();
                    foreach ($fields as $field) {
                        $replayData[$field] = $this->hashMap[$key][$field] ?? null;
                    }
                    break;
                }
                case Package::ACTION_HVALS:
                {
                    $key = $fromPackage->getKey();
                    $replayData = array_values($this->hashMap[$key] ?? []);
                    break;
                }
                case Package::ACTION_HGETALL:
                {
                    $replayData = [];
                    $key = $fromPackage->getKey();
                    foreach ($this->hashMap[$key] ?? [] as $field => $value) {
                        $replayData[] = $field;
                        $replayData[] = $value;
                    }
                    break;
                }
                case Package::DEBUG_READ_PROPERTY:
                {
                    $key = $fromPackage->getKey();
                    if (isset($this->$key)) {
                        $replayData = $this->$key;
                    }
                    break;
                }
            }
        }
        return $replayData;
    }

    /**
     * 根据队列名获取jobId
     * @param mixed $queueName
     * @return int
     */
    private function getJobId($queueName)
    {
        if (!isset($this->jobIds[$queueName])) {
            $this->jobIds[$queueName] = 0;
        }

        return ++$this->jobIds[$queueName];
    }

    /**
     * 初始化Spl队列池
     * @param $key
     * @return \SplQueue
     */
    private function initQueue($key): \SplQueue
    {
        if (!isset($this->queueArray[$key])) {
            $this->queueArray[$key] = new \SplQueue();
        }
        return $this->queueArray[$key];
    }
}