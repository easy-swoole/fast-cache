<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/15
 * Time: 21:46
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class SetTest extends TestCase
{
    public function testSet()
    {
        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);

        $info = Cache::getInstance()->get('siam_set');
        $this->assertEquals('easyswoole', $info);


        $res = Cache::getInstance()->set('siam_set', 'easyswoole', 1);
        $this->assertEquals(true, $res);
        Coroutine::sleep(2);

        $info = Cache::getInstance()->get('siam_set');
        $this->assertEquals(null, $info);
    }

    public function testUnSet()
    {

        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);

        $res = Cache::getInstance()->unset('siam_set');
        $this->assertEquals(true, $res);

        $info = Cache::getInstance()->get('siam_set');
        $this->assertEquals(null, $info);
    }
    public function testExpire()
    {
        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);

        $res = Cache::getInstance()->expire('siam_set', 3);
        $this->assertEquals(true, $res);

        // 测试再取出 值是否变化
        $info = Cache::getInstance()->get('siam_set');
        $this->assertEquals('easyswoole', $info);
        Coroutine::sleep(4);
        // 是否还在
        $info = Cache::getInstance()->get('siam_set');
        $this->assertEquals(null, $info);
    }
    public function testPersist()
    {

        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);
        
        $res = Cache::getInstance()->persist('siam_set');
        $this->assertEquals(true, $res);
    }
}