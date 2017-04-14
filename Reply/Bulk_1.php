<?php

namespace cookpan001\Pipeline\Reply;

class Bulk
{
    public $str = '';
    
    public function __construct($str)
    {
        $this->str = $str;
    }
}