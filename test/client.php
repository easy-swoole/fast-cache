<?php

require_once "../vendor/autoload.php";

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;

go(function (){
//    // **** 投递普通任务 ****
//    $job = new Job();
//    $job->setData("siam1");
//    // $job->setQueue("siam_queue");
//    $job->setQueue("siam_queue2"); // 测试投递到不同的queue  id是否独立递增
//    $jobId = Cache::getInstance()->putJob($job);
//    var_dump($jobId);
//
//
//    // **** 取出可执行任务 ****
//    $job = Cache::getInstance()->getJob('siam_queue2');
//    var_dump($job); // Job对象或者null
//    if ($job === null){
//        echo "没有任务\n";
//    }else{
//        // 执行业务逻辑
//        // 执行完了要删除或者重发，否则超时会自动重发
//        Cache::getInstance()->deleteJob($job);
//    }
//

//    // **** 任务重发 ****
//    // get出来的任务执行失败可以重发
//    $job = new Job();
//    $job->setData("siam1");
//    // $job->setQueue("siam_queue");
//    $job->setQueue("siam_queue2"); // 测试投递到不同的queue  id是否独立递增
//    $jobId = Cache::getInstance()->putJob($job);

//    $job = Cache::getInstance()->getJob('siam_queue2');
//    var_dump($job); // Job对象或者null
//    if ($job === null){
//        echo "没有任务\n";
//    }else{
//        // 执行业务逻辑
//        $doRes = false;
//        if (!$doRes){
//            // 业务逻辑失败,需要重发  如果延迟队列需要马上重发,在这里需要清空delay属性
//            $res = Cache::getInstance()->releaseJob($job);
//            var_dump($res);
//        }else{
//            // 执行完了要删除或者重发，否则超时会自动重发
//            Cache::getInstance()->deleteJob($job);
//        }
//    }

//    // 可以手动指定id和queueName重发  还可以延迟重发
//    $job = new Job();
//    $job->setJobId(1);
//    $job->setQueue("siam_queue2");
//    $job->setDelay(5);
//    $res = Cache::getInstance()->releaseJob($job);
//    var_dump($res);
//
//    $job = Cache::getInstance()->getJob('siam_queue2');
//    var_dump($job); // Job对象或者null


//
//    // **** 投递延时任务 ****
//    $job = new Job();
//    $job->setData("siam1");
//    $job->setQueue("siam_queue_delay");
//    $job->setDelay(5);// 延时5s
//    $jobId = Cache::getInstance()->putJob($job);
//    var_dump($jobId);
//    // 马上取会失败 隔5s取才成功
//    $job = Cache::getInstance()->getJob('siam_queue_delay');
//    var_dump($job);
//
//    // **** 手动把ready任务改为reserve状态 ****
    $job = new Job();
    $job->setData("luffy");
    $job->setQueue("luffy_queue_reserve");

    $jobId = Cache::getInstance()->putJob($job);
    //var_dump($jobId);
    $job->setJobId($jobId);
    var_dump($job);

    var_dump(Cache::getInstance()->reserveJob($job));

    $queueSize = Cache::getInstance()->jobQueueSize("luffy_queue_reserve");
    var_dump($queueSize);
//    // **** 手动把ready任务改为delay状态 ****

//    $job = new Job();
//    $job->setData("luffy");
//    $job->setQueue("luffy_queue_delay");

    //$jobId = Cache::getInstance()->putJob($job);

    //var_dump($jobId);


    //$job = Cache::getInstance()->getJob('luffy_queue_delay');
//    $job->setJobId(1);
//    $job->setDelay(30);
//
//
    //var_dump($job);
    //var_dump(Cache::getInstance()->delayJob($job));


//    $queueSize = Cache::getInstance()->jobQueueSize("luffy_queue_delay");
//    var_dump($queueSize);
//    // **** 删除任务 ****
//    $job = new Job();
//    $job->setJobId(1);
//    Cache::getInstance()->deleteJob($job);
//
//
//
//    // **** 返回现在有什么队列 ****
//    $queues = Cache::getInstance()->jobQueues();
//    var_dump($queues);
//
//    // **** 返回某个队列的长度 ****
//    $queueSize = Cache::getInstance()->jobQueueSize("siam_queue2");
//    $queueSize2 = Cache::getInstance()->jobQueueSize("siam_queue_delay");
//    var_dump($queueSize);
//    var_dump($queueSize2);
//
//    // **** 清空队列 可指定 ****
//    $res = Cache::getInstance()->flushJobQueue();
//    var_dump($res);
//
//    $res = Cache::getInstance()->flushJobQueue("siam_queue2");
//    var_dump($res);
//    $queues = Cache::getInstance()->jobQueues();
//    var_dump($queues);


});