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

可以用 `new FastCache()` 使用默认配置。客户端应在 Socket 就绪后发起请求。缓存进程启动时会清理上次异常退出留下的 Socket 文件。运行中的服务实例须使用各自独立的 Socket 路径。

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
| `get($key)` | 读取普通值。键不存在时返回 `null`，同名键只有队列时也返回 `null`。键为哈希时抛出 `RuntimeException`。 |
| `del($key)` | 删除键及其数据，键存在时返回 `true`。 |
| `hset($key, $field, $value)` | 创建哈希或写入字段，成功返回 `true`。 |
| `hget($key, $field)` | 读取单个哈希字段，键或字段不存在时返回 `null`。 |
| `hscan($key, $cursor = null, $count = 100)` | 按字段插入顺序分页读取哈希，返回 `cursor` 和 `items`。 |
| `initQueue($queueName, $queueCapacity = 1024 * 512)` | 创建指定容量的队列。创建成功返回 `true`，队列已存在时关闭原 Channel 并创建新队列，原有数据丢弃。 |
| `hasInitQueue($queueName)` | 队列已存在时返回 `true`。 |
| `push($key, $value)` | 向 FIFO 队列入队，成功返回 `true`，队列满时等待空位，超时或队列关闭时返回 `false`。 |
| `pop($key)` | 取出最早入队的值，队列空时等待消息，超时或队列关闭时返回 `null`。 |

每个队列使用一个 Swoole 协程 Channel。首次调用 `push()` 或 `pop()` 时，如果队列不存在，会以 `PHP_INT_MAX` 为容量创建，即使同名键已有普通值或哈希，或首次 `pop()` 等待超时。操作与键类型冲突或通信失败时抛出 `RuntimeException`。

所有操作都接受可选的 `?float $timeout = null`，单位为秒，省略时使用 `Config::$timeout`。客户端将其转换为单调时钟上的截止时间 `endLine`。连接、发送和接收共用该截止时间。对 `push()` 和 `pop()`，进程最多等待到 `endLine - 0.1` 秒，为回包预留 0.1 秒。请求到达时若已进入这段余量，进程只尝试立即入队或出队。

值使用 PHP 标量、数组或 `null`。存入的 `null` 与接口返回的缺失值相同。单次请求或响应最大为 8 MiB（含协议头）。

### 哈希分页

首次调用 `hscan()` 时省略游标，之后把上一页返回的 `cursor` 传给下一次调用。`cursor` 为 `null` 时扫描结束。`items` 是由 `['field' => $field, 'value' => $value]` 组成的列表。哈希不存在时，首次调用返回空列表。

`count` 是单页字段数的上限。响应接近 8 MiB 时会提前分页，单个字段及其值超过单页容量时会抛出异常。更新字段保留插入位置，扫描读取各页生成时的值。删除哈希后，旧游标会失效，即使随后用同一个键重建哈希。
