<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/15
 * Time: 21:54
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function testEn()
    {
        $res = Cache::getInstance()->enQueue('siam_queue', 1);
        $this->assertEquals(true, $res);

        $res = Cache::getInstance()->enQueue('siam_queue', 2);
        $this->assertEquals(true, $res);
    }

    public function testDe()
    {
        $res = Cache::getInstance()->deQueue('siam_queue');
        $this->assertEquals(1, $res);
    }

    public function testQueueSize()
    {
        $res = Cache::getInstance()->queueSize('siam_queue');
        $this->assertEquals(1, $res);
    }

    public function testQueueList()
    {
        $res = Cache::getInstance()->queueList();
        $this->assertEquals(['siam_queue'], $res);
    }

    public function testUnset()
    {
        $res = Cache::getInstance()->unsetQueue('siam_queue');
        $this->assertEquals(true, $res);
    }

    public function testQueueSizeAgain()
    {
        $res = Cache::getInstance()->queueList();
        $this->assertEquals([], $res);
    }
    public function testEnAgain()
    {
        $res = Cache::getInstance()->enQueue('siam_queue', 1);
        $this->assertEquals(true, $res);
    }
    public function testFlush()
    {
        $res = Cache::getInstance()->flushQueue();
        $this->assertEquals(true, $res);
    }
    public function testQueueListAgain_2()
    {
        $res = Cache::getInstance()->queueList();
        $this->assertEquals([], $res);
    }
}