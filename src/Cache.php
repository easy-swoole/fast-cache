<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 16:05
 */

namespace EasySwoole\FastCache;


use EasySwoole\Component\Singleton;
use EasySwoole\FastCache\Exception\RuntimeError;

class Cache
{
    use Singleton;

    private $tempDir;
    private $serverName = 'EasySwoole';
    private $onTick;
    private $tickInterval = 5*1000;
    private $onStart;
    private $onShutdown;
    private $processNum = 3;
    private $run = false;

    function __construct()
    {
        $this->tempDir = getcwd();
    }

    public function setTempDir(string $tempDir): Cache
    {
        $this->modifyCheck();
        $this->tempDir = $tempDir;
        return $this;
    }

    public function setProcessNum(int $num):Cache
    {
        $this->modifyCheck();
        $this->processNum = $num;
        return $this;
    }

    public function setServerName(string $serverName): Cache
    {
        $this->modifyCheck();
        $this->serverName = $serverName;
        return $this;
    }

    public function setOnTick($onTick): Cache
    {
        $this->modifyCheck();
        $this->onTick = $onTick;
        return $this;
    }


    public function setTickInterval($tickInterval): Cache
    {
        $this->modifyCheck();
        $this->tickInterval = $tickInterval;
        return $this;
    }


    public function setOnStart($onStart): Cache
    {
        $this->modifyCheck();
        $this->onStart = $onStart;
        return $this;
    }


    public function setOnShutdown(callable $onShutdown): Cache
    {
        $this->modifyCheck();
        $this->onShutdown = $onShutdown;
        return $this;
    }

    function set($key,$value,float $timeout = 0.1)
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('set');
        $com->setValue($value);
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    function get($key,float $timeout = 0.1)
    {
        if($this->processNum <= 0){
            return null;
        }
        $com = new Package();
        $com->setCommand('get');
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    function unset($key,float $timeout = 0.1)
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('unset');
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    function keys($key = null,float $timeout = 0.1):?array
    {
        if($this->processNum <= 0){
            return [];
        }
        $com = new Package();
        $com->setCommand('keys');
        $com->setKey($key);
        $data = [];
        for( $i=0 ; $i < $this->processNum ; $i++){
            $sockFile = $this->tempDir."/{$this->serverName}.FastCacheProcess.{$i}.sock";
            $keys = $this->sendAndRecv('',$com,$timeout,$sockFile);
            if($keys!==null){
                $data = array_merge($data,$keys);
            }
        }
        return $data;
    }

    function flush(float $timeout = 0.1)
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('flush');
        for( $i=0 ; $i < $this->processNum ; $i++){
            $sockFile = $this->tempDir."/{$this->serverName}.FastCacheProcess.{$i}.sock";
            $this->sendAndRecv('',$com,$timeout,$sockFile);
        }
        return true;
    }

    public function enQueue($key,$value,$timeout = 0.1)
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('enQueue');
        $com->setValue($value);
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    public function deQueue($key,$timeout = 0.1)
    {
        if($this->processNum <= 0){
            return null;
        }
        $com = new Package();
        $com->setCommand('deQueue');
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    public function queueSize($key,$timeout = 0.1)
    {
        if($this->processNum <= 0){
            return null;
        }
        $com = new Package();
        $com->setCommand('queueSize');
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    public function unsetQueue($key,$timeout = 0.1):?bool
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('unsetQueue');
        $com->setKey($key);
        return $this->sendAndRecv($key,$com,$timeout);
    }

    /*
     * 返回当前队列的全部key名称
     */
    public function queueList($timeout = 0.1):?array
    {
        if($this->processNum <= 0){
            return [];
        }
        $com = new Package();
        $com->setCommand('queueList');
        $data = [];
        for( $i=0 ; $i < $this->processNum ; $i++){
            $sockFile = $this->tempDir."/{$this->serverName}.FastCacheProcess.{$i}.sock";
            $keys = $this->sendAndRecv('',$com,$timeout,$sockFile);
            if($keys!==null){
                $data = array_merge($data,$keys);
            }
        }
        return $data;
    }

    function flushQueue(float $timeout = 0.1):bool
    {
        if($this->processNum <= 0){
            return false;
        }
        $com = new Package();
        $com->setCommand('flushQueue');
        for( $i=0 ; $i < $this->processNum ; $i++){
            $sockFile = $this->tempDir."/{$this->serverName}.FastCacheProcess.{$i}.sock";
            $this->sendAndRecv('',$com,$timeout,$sockFile);
        }
        return true;
    }

    function attachToServer(\swoole_server $server)
    {
        $list = $this->initProcess();
        foreach ($list as $process){
            /** @var $proces CacheProcess */
            $server->addProcess($process->getProcess());
        }
    }

    public function initProcess():array
    {
        $this->run = true;
        $ret = [];
        $name = "{$this->serverName}.FastCacheProcess";
        for($i = 0;$i < $this->processNum;$i++){
            $config = new ProcessConfig();
            $config->setProcessName("{$name}.{$i}");
            $config->setOnStart($this->onStart);
            $config->setOnShutdown($this->onShutdown);
            $config->setOnTick($this->onTick);
            $config->setTickInterval($this->tickInterval);
            $config->setTempDir($this->tempDir);
            $ret[] = new CacheProcess($config->getProcessName(),$config);
        }
        return $ret;
    }

    private function generateSocket($key):string
    {
        //当以多维路径作为key的时候，以第一个路径为主。
        $list = explode('.',$key);
        $key = array_shift($list);
        $index = base_convert( substr(md5( $key),0,2), 16, 10 )%$this->processNum;
        return $this->tempDir."/{$this->serverName}.FastCacheProcess.{$index}.sock";
    }

    private function sendAndRecv($key,Package $package,$timeout,$socketFile = null)
    {
        if(empty($socketFile)){
            $socketFile = $this->generateSocket($key);
        }
        $client = new UnixClient($socketFile);
        $client->send(serialize($package));
        $ret =  $client->recv($timeout);
        if(!empty($ret)){
            $ret = unserialize($ret);
            if($ret instanceof Package){
                return $ret->getValue();
            }
        }
        return null;
    }

    private function modifyCheck()
    {
        if($this->run){
            throw new RuntimeError('you can not modify configure after init process check');
        }
    }
}