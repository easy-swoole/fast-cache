<?php


namespace EasySwoole\FastCache\Protocol;


use Swoole\Coroutine\Client;

class UnixClient
{
    private $client = null;

    function __construct(string $unixSock,int $maxPackSize,float $timeout)
    {
        $this->client = new Client(SWOOLE_UNIX_STREAM);
        $this->client->set(
            [
                'open_length_check'     => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
                'package_max_length'    => $maxPackSize,
                'timeout'=>$timeout
            ]
        );
        $this->client->connect($unixSock, null, $timeout);
    }

    function __destruct()
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }

    function send(string $rawData)
    {
        if ($this->client->isConnected()) {
            return $this->client->send($rawData);
        } else {
            return false;
        }
    }

    function recv(float $timeout)
    {
        if ($this->client->isConnected()) {
            $ret = $this->client->recv($timeout);
            if (!empty($ret)) {
                return $ret;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
}