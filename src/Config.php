<?php

namespace EasySwoole\FastCache;

use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    public string $serverName = 'EasySwoole';

    public ?string $tempDir = null;

    public float $timeout = 1.0;

    public string $maxMemLimit = '512M';

    protected function initialize(): void
    {
        if ($this->tempDir === null || $this->tempDir === '') {
            $this->tempDir = sys_get_temp_dir();
        }
    }
}
