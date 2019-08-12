<?php


namespace EasySwoole\FastCache\Tests;

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;
use PHPUnit\Framework\TestCase;

class KickBuryQueueTest extends TestCase
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
     * @return Job $job
     * @depends testPutJob
     */
    function testBuryJob(Job $job)
    {
        $res = Cache::getInstance()->buryJob($job);
        $this->assertEquals(true, $res);
        return $job;
    }


    /**
     * 将bury任务恢复到ready中
     *@param Job $job
     *@return bool
     * @depends testBuryJob
     */
    function testKickJob(Job $job)
    {

        $bool = Cache::getInstance()->kickJob($job);
        
        $this->assertEquals(true, $bool);
    }
}