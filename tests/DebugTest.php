<?php


namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use EasySwoole\FastCache\Protocol\Package;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class DebugTest extends TestCase
{
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        Cache::getInstance()->flush();
    }

    public function testSet()
    {
        $key = 'testReadProperty';
        Cache::getInstance()->set('testReadProperty', 'testReadProperty');
        $package = new Package();
        $package->setKey('dataArray');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package, $workerIndex);
        $this->assertEquals(['testReadProperty' => 'testReadProperty'], $ret);


        Cache::getInstance()->expire($key, 1);
        Coroutine::sleep(0.01);
        $package = new Package();
        $package->setKey('ttlKeys');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package, $workerIndex);
        $this->assertEquals(['testReadProperty' => time() + 1], $ret);

        Coroutine::sleep(2 + 0.01);
        $package = new Package();
        $package->setKey('ttlKeys');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package, $workerIndex);
        Coroutine::sleep(0.01);
        $this->assertEquals([], $ret);
    }

    public function testGet()
    {
        $key = 'testKey';
        Cache::getInstance()->set($key, 1);
        $ret = $this->debugReadProperty($key, 'dataArray');
        $this->assertEquals([$key => 1], $ret);
        $this->assertEquals(1, Cache::getInstance()->get($key));
    }

    public function testUnset()
    {
        $key = 'testKey';
        $this->assertTrue(Cache::getInstance()->unset($key));
        Coroutine::sleep(0.01);
        $ret = $this->debugReadProperty($key, 'dataArray');
        $this->assertEquals([], $ret);
        $this->assertEquals(null, Cache::getInstance()->get($key));
    }

    public function testKeys()
    {
        $key1 = 'testKey1';
        $key2 = 'testKey2';
        $this->assertTrue(Cache::getInstance()->set($key1, 1));
        $this->assertTrue(Cache::getInstance()->set($key2, 1));
        $this->assertEquals([$key1, $key2], Cache::getInstance()->keys());
        $this->assertEquals([$key1, $key2], array_keys(array_merge(
            $this->debugReadProperty($key1, 'dataArray'),
            $this->debugReadProperty($key2, 'dataArray')
        )));
    }

    public function testFlush()
    {
        $this->assertTrue(Cache::getInstance()->flush());
        $this->assertEquals([], $this->debugReadProperty('', 'ttlKeys'));
        $this->assertEquals([], $this->debugReadProperty('', 'dataArray'));
        $this->assertEquals([], $this->debugReadProperty('', 'queueArray'));
        $this->assertEquals([], $this->debugReadProperty('', 'hashMap'));
        $this->assertEquals([], $this->debugReadProperty('', 'jobIds'));
        $this->assertEquals([], $this->debugReadProperty('', 'queueArray'));
        $this->assertEquals([], $this->debugReadProperty('', 'buryJob'));
        $this->assertEquals([], $this->debugReadProperty('', 'readyJob'));
        $this->assertEquals([], $this->debugReadProperty('', 'delayJob'));
        $this->assertEquals([], $this->debugReadProperty('', 'reserveJob'));
    }

    public function testExpire()
    {
        $key = 'testKey';
        $this->assertTrue(Cache::getInstance()->set($key, 1, 1));
        Coroutine::sleep(1 + 0.01);
        $this->assertTrue(Cache::getInstance()->expire($key, 1));
        $this->assertEquals(1, Cache::getInstance()->get($key));
        $this->assertEquals([$key => 1], $this->debugReadProperty($key, 'dataArray'));
    }

    public function testPersists()
    {
        $key = 'testKey';
        $this->assertTrue(Cache::getInstance()->set($key, 1, 1));
        $this->assertEquals([$key => time() + 1], $this->debugReadProperty($key, 'ttlKeys'));
        $this->assertTrue(Cache::getInstance()->persist($key));
        $this->assertEmpty($this->debugReadProperty($key, 'ttlKeys'));
    }

    public function testTtl()
    {
        $key = 'testKey';
        $this->assertEquals(null, Cache::getInstance()->ttl($key));
        $this->assertTrue(Cache::getInstance()->set($key, 1, 1));
        $this->assertEquals(1, Cache::getInstance()->ttl($key));
    }

    public function testEnqueue()
    {
        $queue = 'testQueue';
        $this->assertTrue(Cache::getInstance()->enQueue($queue, 1));
        $this->assertArrayHasKey($queue, $this->debugReadProperty($queue, 'queueArray'));
    }

    public function testDequeue()
    {
        $queue = 'testQueue';
        $this->assertEquals(1, Cache::getInstance()->deQueue($queue));
        $this->assertEquals(null, Cache::getInstance()->deQueue($queue));
        $this->assertArrayHasKey($queue, $this->debugReadProperty($queue, 'queueArray'));
    }

    public function testQueueSize()
    {
        $queue = 'testQueue';
        $this->assertEquals(0, Cache::getInstance()->queueSize($queue));
        $this->assertTrue(Cache::getInstance()->enQueue($queue, 1));
        $this->assertEquals(1, Cache::getInstance()->queueSize($queue));
        $this->assertEquals([$queue => new \SplQueue()], $this->debugReadProperty($queue, 'queueArray'));
    }

    public function testUnsetQueue()
    {
        $queue = 'testQueue';
        $queue1 = 'testQueue1';
        $this->assertTrue(Cache::getInstance()->unsetQueue($queue));
        $this->assertFalse(Cache::getInstance()->unsetQueue($queue1));
        $this->assertEquals([], $this->debugReadProperty($queue, 'queueArray'));;
    }

    public function testQueueList()
    {
        $queue = 'testQueue';
        $this->assertEmpty(Cache::getInstance()->queueList());
        $this->assertTrue(Cache::getInstance()->enQueue($queue, 1));
        $this->assertEquals([$queue], Cache::getInstance()->queueList());
        $this->assertEquals([$queue => new \SplQueue()], $this->debugReadProperty($queue, 'queueArray'));
    }

    public function testFlushQueue()
    {
        $queue = 'testQueue';
        $this->assertEquals([$queue => new \SplQueue()], $this->debugReadProperty($queue, 'queueArray'));
        $this->assertTrue(Cache::getInstance()->flushQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'queueArray'));
    }

    public function testPutJob()
    {
        $this->assertTrue(Cache::getInstance()->flush());
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $this->assertEquals(1, Cache::getInstance()->putJob($job));
        $this->assertEquals([], $this->debugReadProperty($queue, 'delayJob'));
        $job->setJobId(1);
        $this->assertEquals([$queue => ['_1' => $job]], $this->debugReadProperty($queue, 'readyJob'));
        $job->setDelay(2);
        $job->setJobId(2);
        $job->setNextDoTime(time() + $job->getDelay());
        $this->assertEquals(2, Cache::getInstance()->putJob($job));
        $this->assertEquals([$queue => ['_2' => $job]], $this->debugReadProperty($queue, 'delayJob'));
    }

    public function testGetJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(1);
        $job->setDequeueTime(time());
        $this->assertEquals($job, Cache::getInstance()->getJob($queue));
    }

    public function testDelayJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setDelay(2);
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(2);
        $this->assertFalse(Cache::getInstance()->delayJob($job));

        $putJob = new Job();
        $putJob->setData($jobData);
        $putJob->setQueue($queue);
        $putJob->setDelay(1);
        $this->assertEquals(3, Cache::getInstance()->putJob($putJob));
        $putJob->setJobId(3);
        \Swoole\Coroutine::sleep(2);
        $putJob->setDelay(0);
        $this->assertFalse(Cache::getInstance()->delayJob($putJob));
        $putJob->setDelay(1);
        $this->assertTrue(Cache::getInstance()->delayJob($putJob));
    }

    public function testGetDelayJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setQueue($queue);
        $job->setData($jobData);
        $job->setJobId(3);
        $job->setDelay(1);
        $job->setNextDoTime(time() + 1);
        $this->assertEquals($job, Cache::getInstance()->getDelayJob($queue));
        $this->assertArrayHasKey($queue, $this->debugReadProperty($queue, 'delayJob'));
    }

    public function testGetReserveJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(1);
        $job->setDequeueTime(time() - 2);
        $this->assertEquals($job, Cache::getInstance()->getReserveJob($queue));
        $this->assertEmpty($this->debugReadProperty($queue, 'reserveJob')[$queue]);
    }

    public function testDeleteJob()
    {
        $queue = 'testQueue';
        $job = new Job();
        $job->setQueue($queue);
        $job->setJobId(2);
        $this->assertNotEmpty($this->debugReadProperty($queue, 'readyJob')[$queue]);
        $this->assertTrue(Cache::getInstance()->deleteJob($job));
        $this->assertEmpty($this->debugReadProperty($queue, 'readyJob')[$queue]);
    }

    public function testJobQueues()
    {
        $queue = 'testQueue';
        $this->assertEquals([$queue], Cache::getInstance()->jobQueues());
    }

    public function testJobQueueSize()
    {
        $queue = 'testQueue';
        $this->assertEquals([
            'ready' => 0,
            'delay' => 0,
            'reserve' => 0,
            'bury' => 0
        ], Cache::getInstance()->jobQueueSize($queue));
    }

    public function testFlushReadyJob()
    {
        $queue = 'testQueue';
        $this->assertEquals($this->buildTrueArray(), Cache::getInstance()->flushReadyJobQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'readyJob'));
    }

    public function testFlushReserveJob()
    {
        $queue = 'testQueue';
        $this->assertEquals($this->buildTrueArray(), Cache::getInstance()->flushReserveJobQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'reserveJob'));
    }

    public function testFlushBuryJob()
    {
        $queue = 'testQueue';
        $this->assertEquals($this->buildTrueArray(), Cache::getInstance()->flushBuryJobQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'buryJob'));
    }

    public function testFlushDelayJob()
    {
        $queue = 'testQueue';
        $this->assertEquals($this->buildTrueArray(), Cache::getInstance()->flushDelayJobQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'delayJob'));
    }

    public function testFlushJobQueue()
    {
        $queue = 'testQueue';
        $this->assertEquals($this->buildTrueArray(), Cache::getInstance()->flushJobQueue());
        $this->assertEmpty($this->debugReadProperty($queue, 'readyJob'));
        $this->assertEmpty($this->debugReadProperty($queue, 'reserveJob'));
        $this->assertEmpty($this->debugReadProperty($queue, 'buryJob'));
        $this->assertEmpty($this->debugReadProperty($queue, 'delayJob'));
    }

    public function testReleaseJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $this->assertFalse(Cache::getInstance()->releaseJob($job));
        $this->assertEquals(4, Cache::getInstance()->putJob($job));
        $job->setJobId(4);
        $this->assertEquals(4, Cache::getInstance()->releaseJob($job));
        $job->setReleaseTimes(1);
        $this->assertEquals([$queue => ['_4' => $job]], $this->debugReadProperty($queue, 'readyJob'));
    }

    public function testReserveJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(4);
        $this->assertTrue(Cache::getInstance()->reserveJob($job));
        $this->assertEmpty($this->debugReadProperty($queue, 'readyJob')[$queue]);
        $job->setReleaseTimes(1);
        $job->setDequeueTime(time());
        $this->assertEquals([$queue => ['_4' => $job]], $this->debugReadProperty($queue, 'reserveJob'));
    }

    public function testBuryJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(4);
        $this->assertTrue(Cache::getInstance()->buryJob($job));
        $this->assertEmpty($this->debugReadProperty($queue, 'reserveJob')[$queue]);
        $job->setDequeueTime(time());
        $job->setReleaseTimes(1);
        $this->assertEquals([$queue => ['_4' => $job]], $this->debugReadProperty($queue, 'buryJob'));
    }

    public function testGetBuryJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(4);
        $job->setDequeueTime(time());
        $job->setReleaseTimes(1);
        $this->assertEquals($job, Cache::getInstance()->getBuryJob($queue));
        $this->assertEmpty($this->debugReadProperty($queue, 'buryJob')[$queue]);
    }

    public function testKickJob()
    {
        $queue = 'testQueue';
        $jobData = 'testJob';
        $job = new Job();
        $job->setData($jobData);
        $job->setQueue($queue);
        $job->setJobId(4);
        $this->assertFalse(Cache::getInstance()->kickJob($job));
        $this->assertEmpty($this->debugReadProperty($queue, 'readyJob')[$queue]);
        $job->setDelay(2);
        $this->assertEquals(5, Cache::getInstance()->putJob($job));
        $job->setJobId(5);
        $this->assertTrue(Cache::getInstance()->buryJob($job));
        $this->assertTrue(Cache::getInstance()->kickJob($job));
        $job->setNextDoTime(time() + 2);
        $this->assertEquals([$queue => ['_5' => $job]], $this->debugReadProperty($queue, 'readyJob'));
    }

    public function testHSet()
    {
        Cache::getInstance()->flush();
        $key = 'hashKey';
        $filed = 'hashFiled';
        $this->assertTrue(Cache::getInstance()->hset($key, $filed, 1));
        $this->assertEquals([$key => [$filed => 1]], $this->debugReadProperty($key, 'hashMap'));
    }

    public function testHGet()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $this->assertEquals([$filed => 1], Cache::getInstance()->hget($key));
        $this->assertEquals(1, Cache::getInstance()->hget($key, $filed));
    }

    public function testHDel()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $this->assertTrue(Cache::getInstance()->hdel($key, $filed));
        $this->assertEquals([$key => []], $this->debugReadProperty($key, 'hashMap'));
        $this->assertTrue(Cache::getInstance()->hdel($key));
        $this->assertEmpty($this->debugReadProperty($key, 'hashMap'));
    }

    public function testHFlush()
    {
        $key = 'hashKey';
        $key1 = 'hashKey1';
        $filed = 'hashFiled';
        $this->assertTrue(Cache::getInstance()->hset($key, $filed, 1));
        $this->assertTrue(Cache::getInstance()->hset($key1, $filed, 1));
        $this->assertEquals(
            [$key => [$filed => 1], $key1 => [$filed => 1]],
            $this->debugReadProperty($key, 'hashMap') + $this->debugReadProperty($key1, 'hashMap')
        );
        Cache::getInstance()->hflush();
        $this->assertEmpty($this->debugReadProperty($key, 'hashMap'));
    }

    public function testHKeys()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertTrue(Cache::getInstance()->hset($key, $filed, 1));
        $this->assertTrue(Cache::getInstance()->hset($key, $filed1, 1));
        $this->assertEquals([$filed, $filed1], Cache::getInstance()->hkeys($key));
    }

    public function testHScan()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertEquals(['data' => [$filed => 1], 'cursor' => 1], Cache::getInstance()->hscan($key, 0, 1));
        $this->assertEquals(['data' => [$filed1 => 1], 'cursor' => 2], Cache::getInstance()->hscan($key, 1, 1));
        $this->assertEquals(['data' => [$filed => 1, $filed1 => 1], 'cursor' => 0], Cache::getInstance()->hscan($key));
    }

    public function testHSetNx()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertTrue(Cache::getInstance()->hsetnx($key, $filed, 2));
        $this->assertEquals([$key => [$filed => 2, $filed1 => 1]], $this->debugReadProperty($key, 'hashMap'));
    }

    public function testHSetExists()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $filed2 = 'hashFiled2';
        $this->assertTrue(Cache::getInstance()->hExists($key, $filed));
        $this->assertTrue(Cache::getInstance()->hExists($key, $filed1));
        $this->assertFalse(Cache::getInstance()->hExists($key, $filed2));
    }

    public function testHLen()
    {
        $key = 'hashKey';
        $this->assertEquals(2, Cache::getInstance()->hLen($key));
    }

    public function testHIncrBy()
    {
        $key = 'hashKey';
        $filed = 'hashFiled';
        $this->assertEquals(3, Cache::getInstance()->hIncrby($key, $filed, 1));
    }

    public function testHmSet()
    {
        Cache::getInstance()->hflush();
        $key = 'hashMKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertTrue(Cache::getInstance()->hMset($key, [$filed => 1, $filed1 => 1]));
        $this->assertEquals([$key => [$filed => 1, $filed1 => 1]], $this->debugReadProperty($key, 'hashMap'));
    }

    public function testHmGet()
    {
        $key = 'hashMKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertEquals([$filed => 1], Cache::getInstance()->hMget($key, [$filed]));
        $this->assertEquals([$filed1 => 1], Cache::getInstance()->hMget($key, [$filed1]));
        $this->assertEquals([$filed => 1, $filed1 => 1], Cache::getInstance()->hMget($key, [$filed, $filed1]));
    }

    public function testHVAls()
    {
        $key = 'hashMKey';
        $this->assertEquals([1, 1], Cache::getInstance()->hVals($key));
    }

    public function testHGetAll()
    {
        $key = 'hashMKey';
        $filed = 'hashFiled';
        $filed1 = 'hashFiled1';
        $this->assertEquals([$filed, 1, $filed1, 1], Cache::getInstance()->hGetAll($key));
    }

    protected function buildTrueArray()
    {
        $workerNum = Cache::getInstance()->getConfig()->getWorkerNum();
        $ret = [];
        for ($i = 0; $i < $workerNum; $i++) {
            $ret[$i] = true;
        }
        return $ret;
    }

    protected function debugReadProperty($key = '', $property = 'dataArray')
    {
        $package = new Package();
        $package->setKey($property);
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package, $workerIndex);
        return $ret;
    }

}