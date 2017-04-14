<?php

namespace cookpan001\Proxy;

class Connection
{
    public $watcher;
    public $clientSocket;
    public $db = 0;
    public $id = null;
    
    public $keys = array();
    
    public function __construct($socket)
    {
        $this->clientSocket = $socket;
    }
    
    public function __destruct()
    {
        $this->_close();
    }
    
    public function _setWatcher($watcher)
    {
        $this->watcher = $watcher;
    }
    
    public function _setId($id)
    {
        $this->id = $id;
    }
    
    public function _addKey($key)
    {
        $this->keys[$key] = $key;
    }
    
    public function _close()
    {
        var_export(__FILE__.'::'.__LINE__."\n");
        if($this->watcher){
            $this->watcher->stop();
        }
        var_export(__FILE__.'::'.__LINE__."\n");
        $this->watcher = null;
        if($this->clientSocket){
            socket_close($this->clientSocket);
        }
        var_export(__FILE__.'::'.__LINE__."\n");
        $this->clientSocket = null;
    }
    
    public function getSocket()
    {
        return $this->clientSocket;
    }
}