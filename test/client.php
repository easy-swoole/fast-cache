<?php

require_once "../vendor/autoload.php";

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Job;

go(function (){
    // **** 投递普通任务 ****
    $job = new Job();
    $job->setData("siam1");
    // $job->setQueue("siam_queue");
    $job->setQueue("siam_queue2"); // 测试投递到不同的queue  id是否独立递增
    $jobId = Cache::getInstance()->putJob($job);
    var_dump($jobId);

    // **** 取出可执行任务 ****
    $job = Cache::getInstance()->getJob('siam_queue2');
    var_dump($job); // Job对象或者null
    // 执行完了要删除或者重发，否则超时会自动重发（超时检测还没做）

    // **** 投递延时任务 ****
    $job = new Job();
    $job->setData("siam1");
    $job->setQueue("siam_queue_delay");
    $job->setDelay(5);// 延时5s
    $jobId = Cache::getInstance()->putJob($job);
    var_dump($jobId);
    // 马上取会失败 隔5s取才成功
    $job = Cache::getInstance()->getJob('siam_queue_delay');
    var_dump($job);

    // **** 手动把ready任务改为reserve状态 ****

    // **** 手动把ready任务改为delay状态 ****

    // **** 删除任务 ****

    // **** 任务重发 ****

    // **** 返回现在有什么队列 ****

    // **** 返回某个队列的长度 ****

    // **** 清空队列 可指定 ****



});