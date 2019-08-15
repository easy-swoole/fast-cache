<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/15
 * Time: 21:51
 */

namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use PHPUnit\Framework\TestCase;

class KeysTest extends TestCase
{
    public function testKeys()
    {

        $res = Cache::getInstance()->set('siam_set', 'easyswoole');
        $this->assertEquals(true, $res);

        $res = Cache::getInstance()->set('siam_set2', 'easyswoole');
        $this->assertEquals(true, $res);
        sleep(1);

        $keys = Cache::getInstance()->keys();
        $this->assertEquals(['siam_set','siam_set2'], $keys);
    }
}