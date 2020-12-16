<?php

use EasySwoole\FastCache\Cache;

require 'vendor/autoload.php';


$http = new swoole_http_server("127.0.0.1", 9501);

Cache::getInstance()->attachToServer($http);


$http->on("request", function ($request, $response) {
    $res = Cache::getInstance()->set('easyswoole', 'easyswoole');
    var_dump($res);
    $res = Cache::getInstance()->get('easyswoole');
    $response->end($res);
});

$http->start();