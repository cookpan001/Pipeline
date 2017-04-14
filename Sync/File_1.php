<?php

namespace cookpan001\Pipeline\Sync;

class File
{
    public $server;
    private $path;
    private $watcher;

    public function __construct($server, $path)
    {
        $this->path = $path;
        $this->server = $server;
    }
    
    public function __destruct()
    {
        $this->server = null;
        $this->watcher = null;
    }
    
    public function setWatcher($watcher)
    {
        $this->watcher = $watcher;
    }

    public function save()
    {
        file_put_contents($this->path, gzdeflate(serialize($this->server->databases), 9));
    }
    
    public function load()
    {
        if(!$this->path){
            return;
        }
        $content = file_get_contents($this->path);
        if(!$content){
            return;
        }
        $this->server->databases = unserialize(gzinflate($content));
    }
}