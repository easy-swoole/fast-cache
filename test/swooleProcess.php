<?php
require_once "../vendor/autoload.php";

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\CacheProcess;

try {
    $processes = Cache::getInstance()->initProcess();
} catch (\EasySwoole\Component\Process\Exception $e) {
    echo "开启失败\n";
}

/** @var CacheProcess $process */
foreach ($processes as $process){
    $process->getProcess()->start();
}

while($ret = \Swoole\Process::wait()) {
    echo "PID={$ret['pid']}\n";
}