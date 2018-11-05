<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace EasySwoole\FastCache\Storage;


interface StorageInterface
{
    public function open(string $file, int $size) : bool;
    public function read(int $size, int $offset) : ?string;
    public function write(string $data, int $offset) : ?int;
    public function close() : bool;
}