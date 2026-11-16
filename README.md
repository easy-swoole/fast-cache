# FastCache

FastCache 通过 Unix Socket 将请求发送给一个 继承 EasySwoole Component `AbstractUnixProcess` 的 Swoole 自定义进程。应用 worker 共享该进程中的内存数据。进程退出后数据会丢失。需要持久化时请使用 Redis 等支持持久化的服务。

## 使用方式

需要 PHP 8.1+、Swoole 5.0+ 和 Composer。Socket 路径请使用应用可写目录下的绝对路径，每个服务实例使用独立路径，Socket 只供可信的应用进程访问。

```php
use EasySwoole\FastCache\Config;
use EasySwoole\FastCache\FastCache;
use Swoole\Http\Server;

require __DIR__ . '/vendor/autoload.php';

$server = new Server('127.0.0.1', 9501);
$cache = new FastCache(new Config(['serverName' => 'my-app', 'tempDir' => '/tmp']));
$cache->attachToServer($server);

$server->on('request', function ($request, $response) use ($cache): void {
    $cache->set('message', 'hello', ttl: 60);
    $response->end($cache->get('message'));
});

$server->start();
```

可以用 `new FastCache()` 使用默认配置。缓存操作须在 Swoole 协程内调用，并等待 Socket 就绪。CLI 中可以用 `Swoole\Coroutine\run()` 建立协程环境。缓存进程启动时会清理上次异常退出留下的 Socket 文件。运行中的服务实例须使用各自独立的 Socket 路径。

## 配置

| 配置项 | 默认值 | 用途 |
| --- | --- | --- |
| `serverName` | `EasySwoole` | 区分服务实例，用于进程名和 Socket 文件名。 |
| `tempDir` | `sys_get_temp_dir()` | Socket 所在的绝对目录，须已存在且应用可写。Socket 文件名为 `{serverName}.fastCache.sock`。 |
| `timeout` | `1.0` | Socket 连接和读写超时，单位为秒，须为有限正数。 |
| `maxMemLimit` | `512M` | 缓存子进程的 PHP `memory_limit`，支持正整数字节数、带 `K`、`M` 或 `G` 后缀的正整数，以及表示无限制的 `-1`。 |

`FastCache` 在构造时读取配置。可以通过 `new Config([...])` 或在构造 `FastCache` 之前设置配置属性。

## 接口

| 方法 | 行为 |
| --- | --- |
| `set($key, $value, $ttl = null, $timeout = null)` | 写入或覆盖普通值，可以指定 TTL，成功返回 `true`。 |
| `get($key)` | 读取普通值，普通值不存在时返回 `null`。 |
| `del($key)` | 删除普通值及其 TTL，普通值存在且未过期时返回 `true`。 |
| `hset($key, $field, $value)` | 创建哈希或写入字段，成功返回 `true`。 |
| `hget($key, $field)` | 读取单个哈希字段，键或字段不存在时返回 `null`。 |
| `hdel($key, $field)` | 删除单个哈希字段，成功返回 `true`，键或字段不存在时返回 `false`。删除最后一个字段时移除该哈希。 |
| `hscan($key, $cursor = null, $count = 100)` | 按字段插入顺序分页读取哈希，返回 `cursor` 和 `items`。 |
| `initQueue($queueName, $queueCapacity = 1024 * 512)` | 创建指定容量的队列。创建成功返回 `true`，队列已存在时关闭原 Channel 并创建新队列，原有数据丢弃。 |
| `delQueue($queueName)` | 关闭并移除队列，丢弃剩余消息。队列存在时返回 `true`，否则返回 `false`。 |
| `hasInitQueue($queueName)` | 队列已存在时返回 `true`。 |
| `push($key, $value)` | 向 FIFO 队列入队，成功返回 `true`，队列满时等待空位，超时或队列关闭时返回 `false`。 |
| `pop($key)` | 取出最早入队的值，队列空时等待消息，超时或队列关闭时返回 `null`。 |

普通值、哈希和队列可以使用同一个 key。普通值用 `del()` 删除，哈希字段用 `hdel()` 删除，队列用 `initQueue()` 清空并重建，或用 `delQueue()` 移除。每个队列使用一个 Swoole 协程 Channel。首次调用 `push()` 或 `pop()` 时，如果队列不存在，会以 `PHP_INT_MAX` 为容量创建，即使同名键已有普通值或哈希，或首次 `pop()` 等待超时。通信失败时抛出 `RuntimeException`。

所有操作都接受可选的 `?float $timeout = null`，单位为秒，省略时使用 `Config::$timeout`。客户端将其转换为单调时钟上的截止时间 `endLine`。连接、请求体传输和响应传输共用该截止时间。服务端读取初始请求头时使用 `Config::$timeout`，读到请求头后的传输使用其中的截止时间。对 `push()` 和 `pop()`，进程最多等待到 `endLine - 0.1` 秒，为回包预留 0.1 秒。请求到达时若已进入这段余量，进程只尝试立即入队或出队。

普通值的 `ttl` 以秒为单位，必须是正整数，从缓存进程写入时开始计时。省略 TTL 或传 `null` 会写入长期有效的值，并清除该普通值原有的过期时间。到期后 `get()` 返回 `null`，子进程内有一个协程通过最小堆按到期时间分批清理过期值。例如 `$cache->set($key, $value, ttl: 60, timeout: 0.5)`。

值可以是 PHP 标量、数组、`null`，以及客户端和缓存进程都能加载的可序列化对象。PHP 资源句柄无法跨进程保留。存入的 `null` 与接口返回的缺失值相同。请求和响应都以 4 字节无符号消息体长度开头。请求长度之后还有 8 字节截止时间，再跟消息体。体积还受进程内存和通信超时约束。

### 哈希分页

首次调用 `hscan()` 时省略游标，之后把上一页返回的 `cursor` 传给下一次调用。`cursor` 为 `null` 时扫描结束。`items` 是由 `['field' => $field, 'value' => $value]` 组成的列表。哈希不存在时，首次调用返回空列表。

`count` 是单页字段数的上限。分页只根据字段数和游标推进。更新字段保留插入位置，扫描读取各页生成时的值。删除哈希或其中任一字段后，旧游标会失效，即使随后用同一个键重建哈希。
