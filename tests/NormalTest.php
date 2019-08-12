<?php


namespace EasySwoole\FastCache\Tests;


use PHPUnit\Framework\TestCase;

class NormalTest extends TestCase
{
    function testGet()
    {
        $this->assertEquals('as','as');
    }
}