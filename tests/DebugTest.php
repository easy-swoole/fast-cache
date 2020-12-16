<?php


namespace EasySwoole\FastCache\Tests;


use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\Protocol\Package;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class DebugTest extends TestCase
{
    function setUp(): void
    {
        Cache::getInstance()->flush();
    }

    function testSet()
    {
        $key = 'testReadProperty';
        Cache::getInstance()->set('testReadProperty','testReadProperty');
        $package = new Package();
        $package->setKey('dataArray');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package,$workerIndex);
        $this->assertEquals(['testReadProperty'=>'testReadProperty'],$ret);


        Cache::getInstance()->expire($key,1);
        $package = new Package();
        $package->setKey('ttlKeys');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package,$workerIndex);
        $this->assertEquals(['testReadProperty'=>time() + 1],$ret);

        Coroutine::sleep(2);
        $package = new Package();
        $package->setKey('ttlKeys');
        $package->setCommand(Package::DEBUG_READ_PROPERTY);
        $workerIndex = Cache::getInstance()->__getWorkerIndex($key);
        $ret = Cache::getInstance()->__debug($package,$workerIndex);
        $this->assertEquals([],$ret);
    }

}