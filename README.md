# FastCache

FastCache 通过 Unix Socket 将请求发送给一个 继承 EasySwoole Component `AbstractUnixProcess` 的 Swoole 自定义进程。应用 worker 共享该进程中的内存数据。进程退出后数据会丢失。需要持久化时请使用 Redis 等支持持久化的服务。

## 使用方式

需要 PHP 8.1+、Swoole 5.0+ 和 Composer。Socket 路径请使用应用可写目录下的绝对路径，每个服务实例使用独立路径。

```php
use EasySwoole\FastCache\Config;
use EasySwoole\FastCache\FastCache;
use Swoole\Http\Server;

require __DIR__ . '/vendor/autoload.php';

$server = new Server('127.0.0.1', 9501);
$cache = new FastCache(new Config(['serverName' => 'my-app', 'tempDir' => '/tmp']));
$cache->attachToServer($server);

$server->on('request', function ($request, $response) use ($cache): void {
    $cache->set('message', 'hello');
    $response->end($cache->get('message'));
});

$server->start();
```

可以用 `new FastCache()` 使用默认配置。客户端应在 Socket 就绪后发起请求。如果进程异常退出导致 Socket 文件残留，确认原进程已停止后再移除残留文件。

## 配置

| 配置项 | 默认值 | 用途 |
| --- | --- | --- |
| `serverName` | `EasySwoole` | 区分服务实例，用于进程名和 Socket 文件名。 |
| `tempDir` | `sys_get_temp_dir()` | Socket 所在的绝对目录，须已存在且应用可写。Socket 文件名为 `{serverName}.fastCache.sock`。 |
| `timeout` | `1.0` | Socket 连接和读写超时，单位为秒，须为有限正数。 |

`FastCache` 在构造时读取配置。可以通过 `new Config([...])` 或在构造 `FastCache` 之前设置配置属性。

## 接口

| 方法 | 行为 |
| --- | --- |
| `set($key, $value)` | 写入普通值，覆盖原有值、哈希或队列，成功返回 `true`。 |
| `get($key)` | 读取普通值或整个哈希，键不存在时返回 `null`。 |
| `del($key)` | 删除键及其数据，键存在时返回 `true`。 |
| `hset($key, $field, $value)` | 创建哈希或写入字段，成功返回 `true`。 |
| `hget($key, $field)` | 读取单个哈希字段，键或字段不存在时返回 `null`。 |
| `initQueue($queueName, $queueCapacity = 1024 * 512)` | 创建指定容量的队列。创建成功返回 `true`，队列已存在时关闭原 Channel 并创建新队列，原有数据丢弃。 |
| `hasInitQueue($queueName)` | 队列已存在时返回 `true`。 |
| `push($key, $value)` | 向 FIFO 队列入队，成功返回 `true`，队列满时等待空位，超时或队列关闭时返回 `false`。 |
| `pop($key)` | 取出最早入队的值，队列空时等待消息，超时或队列关闭时返回 `null`。 |

每个队列使用一个 Swoole 协程 Channel。首次调用 `push()` 或 `pop()` 时，如果队列不存在，会以 `PHP_INT_MAX` 为容量创建，即使首次 `pop()` 等待超时。操作与键类型冲突或通信失败时抛出 `RuntimeException`。

所有操作都接受可选的 `?float $timeout = null`，单位为秒，省略时使用 `Config::$timeout`。客户端将其转换为单调时钟上的截止时间 `endLine`。连接、发送和普通操作的接收共用该截止时间。对 `push()` 和 `pop()`，进程等待至 `endLine + 0.1` 秒，客户端接收至 `endLine + 0.2` 秒，为回包留出 0.1 秒。

值使用 PHP 标量、数组或 `null`。存入的 `null` 与接口返回的缺失值相同。单次请求或响应最大为 8 MiB（含协议头）。
