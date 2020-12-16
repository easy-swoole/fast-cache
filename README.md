# FastCache
EasySwoole FastCache组件通过新开进程,使用SplArray存储,unix sock 高速通信方式,实现了多进程共享数据.

### 示例代码
```php
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
```

## 内存问题

数据分散在进程内，一个进程可能需要占用很大的内存，因此请根据实际业务量配置内存大小。

## 单元测试
### 服务启动
```bash
php example.php
```
### 执行测试用例
```
 php vendor/bin/co-phpunit tests
```
