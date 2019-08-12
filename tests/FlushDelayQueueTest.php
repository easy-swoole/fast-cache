<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/12
 * Time: 22:59
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use PHPUnit\Framework\TestCase;

class FlushDelayQueueTest extends TestCase
{

    /**
     * 新增ready job
     */
    function testSet()
    {
        $job = new Job();
        $job->setQueue('siam_test');
        $job->setData('测试数据');
        $job->setDelay(50);
        $res = Cache::getInstance()->putJob($job);
        $this->assertIsInt($res);

        $job->setJobId($res);
        return $job->getQueue();
    }

    /**
     * 清理bury队列
     * @param string $queueName
     * @return string
     * @depends testSet
     */
    function testFlushDelay(string $queueName)
    {
        $res = Cache::getInstance()->flushDelayJobQueue($queueName);

        $this->assertEquals(true, $res);

        return $queueName;
    }

    /**
     * delay size
     * @param string $queueName
     * @depends testFlushDelay
     */
    function testDelaySize(string $queueName)
    {
        $res = Cache::getInstance()->jobQueueSize($queueName);
        $this->assertEquals(0, $res['delay']);
    }
}