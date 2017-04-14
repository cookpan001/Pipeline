<?php

namespace cookpan001\Pipeline\Sync;

class Sqlite
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}