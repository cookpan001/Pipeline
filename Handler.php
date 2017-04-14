<?php

namespace cookpan001\Proxy;

class Handler
{
    /**
     * @var Server
     */
    private $server;
    public $timers;

    public function __construct($server)
    {
        $this->server = $server;
        $this->timers = array();
    }
    
    public function redis($name, ...$arugments)
    {
        list($key, $score, $member) = $arr;
        $database = $this->server->databases[$connection->db()];
        return $this->setTimer($database, $key, $score, $member);
    }
    
    public function mysql($name, ...$arugments)
    {
        list($key, $score, $member) = $arr;
        $database = $this->server->databases[$connection->db()];
        return $this->setTimer($database, $key, $score, $member);
    }
}