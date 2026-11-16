<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Protocol;

enum OpCode: string
{
    case SET = 'set';
    case GET = 'get';
    case DEL = 'del';
    case HSET = 'hset';
    case HGET = 'hget';
    case HDEL = 'hdel';
    case HSCAN = 'hscan';
    case INIT_QUEUE = 'initQueue';
    case DEL_QUEUE = 'delQueue';
    case HAS_INIT_QUEUE = 'hasInitQueue';
    case PUSH = 'push';
    case POP = 'pop';
}
