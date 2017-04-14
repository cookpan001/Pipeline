<?php

namespace cookpan001\Proxy\Reply;

class Bulk
{
    public $str = '';
    
    public function __construct($str)
    {
        $this->str = $str;
    }
}