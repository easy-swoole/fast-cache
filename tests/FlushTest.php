<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/15
 * Time: 21:53
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use PHPUnit\Framework\TestCase;

class FlushTest extends TestCase
{
    public function testFlush()
    {


        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);

        Cache::getInstance()->flush();

        $keys = Cache::getInstance()->keys();
        
        $this->assertEquals([], $keys);

    }
}