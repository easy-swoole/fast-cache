<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 16:06
 */

namespace EasySwoole\FastCache;

use EasySwoole\Component\Process\Exception;
use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Spl\SplArray;
use SplQueue;
use Swoole\Coroutine\Socket;
use Throwable;

class CacheProcess extends AbstractUnixProcess
{
    /**
     * Spl数组存放当前的缓存内容
     * @var SplArray
     */
    protected $splArray;

    /**
     * 存放Spl队列
     * @var array
     */
    protected $queueArray = [];

    /**
     * 带有过期时间的Key
     * @var array
     */
    protected $ttlKeys = [];

    /**
     * 进程初始化并开始监听Socket
     * @param $args
     * @throws Exception
     */
    public function run($args)
    {
        /** @var $processConfig CacheProcessConfig */
        $processConfig = $this->getConfig();
        $this->splArray = new SplArray();

        // 进程启动时执行
        if (is_callable($processConfig->getOnStart())) {
            try {
                $ret = call_user_func($processConfig->getOnStart(),$processConfig);
                if ($ret instanceof SyncData) {
                    $this->splArray   = $ret->getArray();
                    $this->queueArray = $ret->getQueueArray();
                    $this->ttlKeys    = $ret->getTtlKeys();
                }
            } catch (Throwable $throwable) {
                $this->onException($throwable);
            }
        }

        // 设定落地时间定时器
        if (is_callable($processConfig->getOnTick())) {
            $this->addTick($processConfig->getTickInterval(), function () use ($processConfig) {
                try {
                    $data = new SyncData();
                    $data->setArray($this->splArray);
                    $data->setQueueArray($this->queueArray);
                    $data->setTtlKeys($this->ttlKeys);
                    call_user_func($processConfig->getOnTick(), $data,$processConfig);
                } catch (Throwable $throwable) {
                    $this->onException($throwable);
                }
            });
        }

        // 过期Key自动回收(至少499ms执行一次保证1秒内执行2次过期判断)
        $this->addTick(499, function () use ($processConfig) {
            try {
                if (!empty($this->ttlKeys)) {
                    mt_srand();
                    $keys = array_keys($this->ttlKeys);
                    shuffle($keys);
                    $checkKeys = array_slice($keys, 0, 100);  // 每次随机检查100个过期
                    if (is_array($checkKeys) && count($checkKeys) > 0) {
                        foreach ($checkKeys as $ttlKey) {
                            $ttlExpire = $this->ttlKeys[$ttlKey];
                            if ($ttlExpire < time()) {
                                unset($this->ttlKeys[$ttlKey], $this->splArray[$ttlKey]);
                            }
                        }
                    }
                }
            } catch (Throwable $throwable) {
                $this->onException($throwable);
            }
        });

        parent::run($processConfig);
    }

    /**
     * 初始化Spl队列池
     * @param $key
     * @return SplQueue
     */
    private function initQueue($key): SplQueue
    {
        if (!isset($this->queueArray[$key])) {
            $this->queueArray[$key] = new SplQueue();
        }
        return $this->queueArray[$key];
    }

    /**
     * 获取当前Spl队列池
     * @return array
     */
    public function getQueueArray(): array
    {
        return $this->queueArray;
    }

    /**
     * 设置当前Spl队列池
     * @param array $queueArray
     */
    public function setQueueArray(array $queueArray): void
    {
        $this->queueArray = $queueArray;
    }

    /**
     * 获取当前Spl数组
     * @return mixed
     */
    public function getSplArray()
    {
        return $this->splArray;
    }

    /**
     * 设置当前Spl数组
     * @param mixed $splArray
     */
    public function setSplArray($splArray): void
    {
        $this->splArray = $splArray;
    }

    /**
     * 进程退出时落地数据
     * @return void
     */
    public function onShutDown()
    {
        $onShutdown = $this->getConfig()->getOnShutdown();
        if (is_callable($onShutdown)) {
            try {
                $data = new SyncData();
                $data->setArray($this->splArray);
                $data->setQueueArray($this->queueArray);
                $data->setTtlKeys($this->ttlKeys);
                call_user_func($onShutdown, $data,$this->getConfig());
            } catch (Throwable $throwable) {
                $this->onException($throwable);
            }
        }
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
        return;
    }

    /**
     * 异常处理
     * @param Throwable $throwable
     * @param mixed ...$args
     */
    protected function onException(Throwable $throwable, ...$args)
    {
        trigger_error("{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}");
    }

    /**
     * 执行命令
     * @param $commandPayload
     * @return Package
     */
    protected function executeCommand(?string $commandPayload): Package
    {
        $replyPackage = new Package();
        $fromPackage = unserialize($commandPayload);
        if ($fromPackage instanceof Package) { // 进入业务处理流程
            switch ($fromPackage->getCommand()) {
                case 'set':
                    {
                        $replyPackage->setValue(true);
                        $key = $fromPackage->getKey();
                        $value = $fromPackage->getValue();

                        // 按照redis的逻辑 当前key没有过期 set不会重置ttl 已过期则重新设置
                        $ttl = $fromPackage->getOption($fromPackage::OPTIONS_TTL);
                        if (!array_key_exists($key, $this->ttlKeys) || $this->ttlKeys[$key] < time()) {
                            if (!is_null($ttl)) {
                                $this->ttlKeys[$key] = time() + $ttl;
                            }
                        }

                        $this->splArray->set($key, $value);
                        break;
                    }
                case 'get':
                    {
                        $key = $fromPackage->getKey();

                        // 取出之前需要先判断当前是否有ttl 如果有ttl设置并且已经过期 立刻删除key
                        if (array_key_exists($key, $this->ttlKeys) && $this->ttlKeys[$key] < time()) {
                            unset($this->ttlKeys[$key]);
                            $this->splArray->unset($key);
                            $replyPackage->setValue(null);
                        } else {
                            $replyPackage->setValue($this->splArray->get($fromPackage->getKey()));
                        }

                        break;
                    }
                case 'unset':
                    {
                        $replyPackage->setValue(true);
                        unset($this->ttlKeys[$fromPackage->getKey()]); // 同时移除TTL
                        $this->splArray->unset($fromPackage->getKey());
                        break;
                    }
                case 'keys':
                    {
                        $key = $fromPackage->getKey();
                        $keys = $this->splArray->keys($key);
                        $time = time();
                        foreach ($this->ttlKeys as $ttlKey => $ttl) {
                            if ($ttl < $time) {
                                unset($keys[$ttlKey], $this->ttlKeys[$ttlKey]);  // 立刻释放过期的ttlKey
                            }
                        }
                        $replyPackage->setValue($this->splArray->keys($key));
                        break;
                    }
                case 'flush':
                    {
                        $replyPackage->setValue(true);
                        $this->ttlKeys = [];  // 同时移除全部TTL时间
                        $this->splArray = new SplArray();
                        break;
                    }
                case 'expire':
                    {
                        $replyPackage->setValue(false);
                        $key = $fromPackage->getKey();
                        $ttl = $fromPackage->getOption($fromPackage::OPTIONS_TTL);

                        // 不能给当前没有的Key设置TTL
                        if (array_key_exists($key, $this->splArray)) {
                            if (!is_null($ttl)) {
                                $this->ttlKeys[$key] = time() + $ttl;
                                $replyPackage->setValue(true);
                            }
                        }

                        break;
                    }
                case 'persist':
                    {
                        $replyPackage->setValue(true);
                        $key = $fromPackage->getKey();
                        unset($this->ttlKeys[$key]);
                        break;
                    }
                case 'ttl':
                    {
                        $replyPackage->setValue(null);
                        $key = $fromPackage->getKey();
                        $time = time();

                        // 不能查询当前没有的Key
                        if (array_key_exists($key, $this->splArray) && array_key_exists($key, $this->ttlKeys)) {
                            $expire = $this->ttlKeys[$key];
                            if ($expire > $time) {  // 有剩余时间时才会返回剩余ttl 否则返回null表示已经过期或未设置 不区分主动过期和key不存在的情况
                                $replyPackage->setValue($expire - $time);
                            }
                        }
                        break;
                    }
                case 'enQueue':
                    {
                        $que = $this->initQueue($fromPackage->getKey());
                        $data = $fromPackage->getValue();
                        if ($data !== null) {
                            $que->enqueue($fromPackage->getValue());
                            $replyPackage->setValue(true);
                        } else {
                            $replyPackage->setValue(false);
                        }
                        break;
                    }
                case 'deQueue':
                    {
                        $que = $this->initQueue($fromPackage->getKey());
                        if ($que->isEmpty()) {
                            $replyPackage->setValue(null);
                        } else {
                            $replyPackage->setValue($que->dequeue());
                        }
                        break;
                    }
                case 'queueSize':
                    {
                        $que = $this->initQueue($fromPackage->getKey());
                        $replyPackage->setValue($que->count());
                        break;
                    }
                case 'unsetQueue':
                    {
                        if (isset($this->queueArray[$fromPackage->getKey()])) {
                            unset($this->queueArray[$fromPackage->getKey()]);
                            $replyPackage->setValue(true);
                        } else {
                            $replyPackage->setValue(false);
                        }
                        break;
                    }
                case 'queueList':
                    {
                        $replyPackage->setValue(array_keys($this->queueArray));
                        break;
                    }
                case 'flushQueue':
                    {
                        $this->queueArray = [];
                        $replyPackage->setValue(true);
                        break;
                    }
            }
        }
        return $replyPackage;
    }
}