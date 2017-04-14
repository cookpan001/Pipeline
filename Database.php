<?php

namespace cookpan001\Pipeline;

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