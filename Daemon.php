<?php

namespace cookpan001\Pipeline;

class Daemon
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
    
    public function quit(Connection $connection, $arr)
    {
        if(!isset($this->server->client[$connection->id])){
            socket_close($connection->clientSocket);
            unset($this->server->client[$connection->id]);
            foreach($connection->keys as $key){
                unset($this->server->timerKeys[$key][$connection->id]);
            }
            $this->server->log("quit: {$connection->id}");
        }
        return 'OK';
    }
    
    public function ping(Connection $connection, $arr)
    {
        return 'PONG';
    }
    
    public function info(Connection $connection, $arr)
    {
        $diff = time() - $this->server->uptime;
        $ret = array(
            '# Server',
            'version:1.0.0',
            'build_id:19c98b4cf0f98c9a',
            'mode:standalone',
            'os:'.php_uname('s'),
            'arch_bits:'.php_uname('m'),
            'uptime_in_seconds:'.$diff,
            'uptime_in_days:'.intval($diff/86400),
            'process_id:'.getmypid(),
            'tcp_port:'.$this->server->port,
            'bind_host:'.$this->server->host,
            'config_file:~',
            '',
            '# Clients',
            'connected_clients:'.count($this->server->client),
            '',
            '# Stats',
            'total_connections_received:'.$this->server->allConnections,
            'total_commands_processed:'.$this->server->allCmds,
            '',
            '# Memory',
            'used_memory:'.memory_get_usage(),
            'used_memory_human:'.round(memory_get_usage()/1024/1024, 2).'M',
            'used_memory_peak:'. memory_get_peak_usage(),
            'used_memory_peak_human:' . round(memory_get_peak_usage()/1024/1024, 2).'M',
            '',
            '# Keyspace',
        );
        foreach($this->server->databases as $database){
            $ret[] = 'db'.$database->db.':keys='.count($database->keys);
        }
        $ret[] = '';
        return new Reply\Bulk(implode("\r\n",$ret));
    }
}