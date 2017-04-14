<?php

namespace cookpan001\Proxy;

class Database
{
    public $db;
    public $keys = array();
    public $delayed = array();
    
    public function __construct($db)
    {
        $this->db = $db;
    }
}