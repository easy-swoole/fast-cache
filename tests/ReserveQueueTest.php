<?php


namespace EasySwoole\FastCache\Tests;

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use PHPUnit\Framework\TestCase;

class ReserveQueueTest extends TestCase
{

    function testPutJob()
    {
        $job = new Job();
        $job->setQueue('LuffyQAQ_queue_reserve');
        $job->setData('LuffyQAQ');
        $res = Cache::getInstance()->putJob($job);

        $this->assertIsInt($res);

        $job->setJobId($res);
        return $job;


    }

    /**
     * @param Job $job
     * @return string
     * @depends testPutJob
     */
    function testReserveJob(Job $job)
    {
        $res = Cache::getInstance()->reserveJob($job);
        $this->assertEquals(true,$res);
        return $job->getQueue();
    }

    /**
     * 获取getReserve
     * @param string $queueName
     * @return Job
     * @depends testReserveJob
     */
    function testGetDelay(string $queueName)
    {
        $res = Cache::getInstance()->getReserveJob($queueName);

        $this->assertInstanceOf(Job::class,$res);

    }
}