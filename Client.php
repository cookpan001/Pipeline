<?php

class Client
{
    const OPTION_WORKER = 1;
    const OPTION_MULTI = 2;
    const OPTION_REQUEST = 4;
    const OPTION_REPLY = 8;
    const OPTION_HEARTBEAT = 16;
    const OPTION_NOTIFY = 32;
    const OPTION_TO_WORKER = 64;
    const OPTION_TO_SERVER = 128;
    const OPTION_WORKER_STATUS = 256;
    const OPTION_SERVER_STATUS = 512;
    
    private $port;

    /**
     * @var callable
     */
    private $consumer;

    const SIZE = 4;
    const END = "\r\n";
    
    private $host;
    public $socket = null;
    
    public function __construct($host = '127.0.0.1', $port = 6379, Callable $consumer = null)
    {
        $this->port = $port;
        $this->host = $host;
        if(is_null($consumer)){
            $consumer = array($this, 'test');
        }
        $this->consumer = $consumer;
    }
    
    public function __destruct()
    {
        if($this->socket){
            $this->log('closing socket connection');
            socket_close($this->socket);
        }
    }
    
    public function connect()
    {
        while(true){
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($this->socket === FALSE) {
                //echo "socket_create() failed: reason: ".socket_strerror(socket_last_error()) . "\n";
                sleep(1);
                continue;
            }
            if(!socket_connect($this->socket, $this->host, $this->port)){
                //echo "socket_connect() failed: reason: ".socket_strerror(socket_last_error()) . "\n";
                sleep(1);
                continue;
            }
            $this->log("connected to {$this->host}:{$this->port}");
            break;
        }
        return $this;
    }
    
    public function log($message)
    {
        list($m1, ) = explode(' ', microtime());
        $date = date('Y-m-d H:i:s') . substr($m1, 1);
        echo $date."\t".$message."\n";
    }
    
    public function serialize($data)
    {
        $tmp = msgpack_pack($data);
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($response)
    {
        $ret = msgpack_unpack($response);
        return $ret;
    }
    
    public function __call($name, $arguments)
    {
        return $this->request(msgpack_pack(func_get_args()));
    }
    
    public function request($message, $kind = self::OPTION_REQUEST)
    {
        if(empty($this->socket)){
            $this->log('empty socket.');
            $this->connect();
        }
        $buffer = $this->serialize(array($kind, 0, $message));
        $writeLen = socket_write($this->socket, $buffer);
        if(false === $writeLen){
            $this->log('write len === false');
            return false;
        }
        $writeErrno = socket_last_error();
        $this->log('write errno '.$writeErrno . ', '. socket_strerror($writeErrno));
        if(EPIPE == $writeErrno || ECONNRESET == $writeErrno){
            socket_close($this->socket);
            $this->socket = null;
            return false;
        }
        $lenStr = socket_read($this->socket, self::SIZE);
        if(false === $lenStr || '' === $lenStr){
            return false;
        }
        $sizeArr = unpack('N', $lenStr);
        $response = socket_read($this->socket, array_pop($sizeArr));
        if(false === $response){
            return false;
        }
        $readErrno = socket_last_error();
        $this->log('read errno '.$readErrno . ', '. socket_strerror($readErrno));
        if(EPIPE == $readErrno || ECONNRESET == $readErrno){
            socket_close($this->socket);
            $this->socket = null;
            return false;
        }
        if(strlen($response) == 0){
            $this->log('empty response');
            return false;
        }
        $data = $this->unserialize($response);
        if($this->consumer){
            return call_user_func_array($this->consumer, $data);
        }
        $this->log('no consumer function ');
        return 0;
    }
    
    public function test($kind, $seqId, $message)
    {
        $info = msgpack_unpack($message);
        $this->log("receive: {$kind}\t{$seqId}\t". json_encode($info));
        return $message;
    }
}
ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';
$app = new Client();
$app->connect();
$i = 0;
$t1 = microtime(true);
while($i < 4000){
    echo $i."\n";
    $app->hello($i);
    ++$i;
}
$t2 = microtime(true);
$app->log('total time used: '.(($t2 - $t1) * 1000));