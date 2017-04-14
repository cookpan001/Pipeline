<?php

namespace cookpan001\Proxy\Sync;

class Sqlite
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}