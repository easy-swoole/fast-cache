<?php
require 'vendor/autoload.php';

use EasySwoole\FastCache\Cache;

$processes = Cache::getInstance()->initProcess();

foreach ($processes as $process){
    $process->getProcess()->start();
}

while($ret = \Swoole\Process::wait()) {
    echo "PID={$ret['pid']}\n";
}