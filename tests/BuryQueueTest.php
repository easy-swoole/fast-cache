<?php


namespace EasySwoole\FastCache\Tests;

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use PHPUnit\Framework\TestCase;

class BuryQueueTest extends TestCase
{

    function testPutJob()
    {
        $job = new Job();
        $job->setQueue('LuffyQAQ_queue_bury');
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
    function testBuryJob(Job $job)
    {
        $res = Cache::getInstance()->buryJob($job);
        $this->assertEquals(true, $res);
        return $job->getQueue();
    }

    /**
     * 获取GetBury
     * @param string queueName
     * @return Job $job
     * @depends testBuryJob
     */
    function testGetBury(string $queueName)
    {
        $res = Cache::getInstance()->getBuryJob($queueName);
        $this->assertInstanceOf(Job::class, $res);
        return $res;

    }

}