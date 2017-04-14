<?php

namespace cookpan001\Proxy\Sync;

class Redis
{
    private $path;

    public function __construct($scheme = 'tcp://127.0.0.1:6379')
    {
        $this->path = $scheme;
    }
}