<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/8/13
 * Time: 12:37
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\CacheProcessConfig;
use EasySwoole\FastCache\Job;
use EasySwoole\FastCache\SyncData;
use EasySwoole\Utility\File;
use PHPUnit\Framework\TestCase;

class QueueSyncDataTest  extends TestCase
{
    // 测试流程： 先启动服务，（需要设置落地）  然后注释testQueueSize  运行set等待tick落地  然后结束服务 重新启动
    // 然后注释set  运行testQueueSize判断


    /**
     * 启动设置就判断队列的长度（是否有恢复）
     */
    function testQueueSize()
    {
        $res = Cache::getInstance()->jobQueueSize('siam_test_sync');

        $this->assertNotEquals(0,$res['ready']);
        $this->assertNotEquals(0,$res['delay']);
        $this->assertNotEquals(0,$res['bury']);

        // 新投递一个 jobId是否继承
        $job = new Job();
        $job->setQueue('siam_test_sync');
        $job->setData("测试");
        $jobId = Cache::getInstance()->putJob($job);

        $this->assertGreaterThan(3, $jobId);
    }
    /**
     * 设置数据到fast-cache中
     */
    // function testSet()
    // {
    //     $job = new Job();
    //     $job->setQueue('siam_test_sync');
    //     $job->setData("测试");
    //     $jobId = Cache::getInstance()->putJob($job);
    //
    //     $this->assertIsInt($jobId);
    //
    //     $job = new Job();
    //     $job->setQueue('siam_test_sync');
    //     $job->setData("测试延迟");
    //     $job->setDelay(3000);
    //     $jobId = Cache::getInstance()->putJob($job);
    //     $this->assertIsInt($jobId);
    //
    //     $job = new Job();
    //     $job->setQueue('siam_test_sync');
    //     $job->setData("测试bury");
    //     $jobId = Cache::getInstance()->putJob($job);
    //     $job->setJobId($jobId);
    //     Cache::getInstance()->buryJob($job);
    // }

}