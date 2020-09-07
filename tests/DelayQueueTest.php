<?php


namespace EasySwoole\FastCache\Tests;

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use PHPUnit\Framework\TestCase;

class DelayQueueTest extends TestCase
{

    function testPutJob()
    {
        $job = new Job();
        $job->setQueue('LuffyQAQ_queue_delay');
        $job->setData('LuffyQAQ');
        $res = Cache::getInstance()->putJob($job);

        $this->assertIsInt($res);

        $job->setJobId($res);
        $job->setDelay(30);
        return $job;


    }

    /**
     * @param Job $job
     * @return string
     * @depends testPutJob
     */
    function testDelayJob(Job $job)
    {
        $res = Cache::getInstance()->delayJob($job);
        $this->assertEquals(true,$res);
        return $job->getQueue();
    }

    /**
     * 获取getDelay
     * @param string $queueName
     * @return Job $job
     * @depends testDelayJob
     */
    function testGetDelay(string $queueName)
    {
        $res = Cache::getInstance()->getDelayJob($queueName);

        $this->assertInstanceOf(Job::class,$res);

    }
}