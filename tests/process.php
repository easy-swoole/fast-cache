<?php
require 'vendor/autoload.php';

use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\CacheProcessConfig;
use EasySwoole\FastCache\SyncData;
use EasySwoole\Utility\File;


// 设置落地
Cache::getInstance()->setTickInterval(5 * 1000);//设置定时频率
Cache::getInstance()->setOnTick(function (SyncData $SyncData, CacheProcessConfig $cacheProcessConfig) {
    $data = [
        'data'  => $SyncData->getArray(),
        'queue' => $SyncData->getQueueArray(),
        'ttl'   => $SyncData->getTtlKeys(),
        // queue支持
        'jobIds'     => $SyncData->getJobIds(),
        'readyJob'   => $SyncData->getReadyJob(),
        'reserveJob' => $SyncData->getReserveJob(),
        'delayJob'   => $SyncData->getDelayJob(),
        'buryJob'    => $SyncData->getBuryJob(),
    ];
    $path = __DIR__ . '/FastCacheData/' . $cacheProcessConfig->getProcessName();
    File::createFile($path,serialize($data));
});

// 启动时将存回的文件重新写入
Cache::getInstance()->setOnStart(function (CacheProcessConfig $cacheProcessConfig) {
    $path = __DIR__ . '/FastCacheData/' . $cacheProcessConfig->getProcessName();
    if(is_file($path)){
        $data = unserialize(file_get_contents($path));
        $syncData = new SyncData();
        $syncData->setArray($data['data']);
        $syncData->setQueueArray($data['queue']);
        $syncData->setTtlKeys(($data['ttl']));
        // queue支持
        $syncData->setJobIds($data['jobIds']);
        $syncData->setReadyJob($data['readyJob']);
        $syncData->setReserveJob($data['reserveJob']);
        $syncData->setDelayJob($data['delayJob']);
        $syncData->setBuryJob($data['buryJob']);
        return $syncData;
    }
});

// 在守护进程时,php easyswoole stop 时会调用,落地数据
Cache::getInstance()->setOnShutdown(function (SyncData $SyncData, CacheProcessConfig $cacheProcessConfig) {
    $data = [
        'data'  => $SyncData->getArray(),
        'queue' => $SyncData->getQueueArray(),
        'ttl'   => $SyncData->getTtlKeys(),
        // queue支持
        'jobIds'     => $SyncData->getJobIds(),
        'readyJob'   => $SyncData->getReadyJob(),
        'reserveJob' => $SyncData->getReserveJob(),
        'delayJob'   => $SyncData->getDelayJob(),
        'buryJob'    => $SyncData->getBuryJob(),
    ];
    $path = __DIR__ . '/FastCacheData/' . $cacheProcessConfig->getProcessName();
    File::createFile($path,serialize($data));
});

$processes = Cache::getInstance()->initProcess();

foreach ($processes as $process){
    $process->getProcess()->start();
}

while($ret = \Swoole\Process::wait()) {
    echo "PID={$ret['pid']}\n";
}