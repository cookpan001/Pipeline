<?php

class Worker
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
     * 响应处理函数
     * @var callable
     */
    private $consumer;
    /**
     * 请求处理函数
     * @var callable
     */
    private $worker;

    const SIZE = 1500;
    const END = "\r\n";
    
    private $host;
    public $socket = null;
    private $watcher = null;
    
    public function __construct(Callable $worker = null, Callable $consumer = null, $host = '127.0.0.1', $port = 6379)
    {
        $this->port = $port;
        $this->host = $host;
        if(is_null($consumer)){
            $consumer = array($this, 'test');
        }
        if(is_null($worker)){
            $worker = array($this, 'test');
        }
        $this->consumer = $consumer;
        $this->worker = $worker;
        register_shutdown_function(array($this, 'close'));
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public function close()
    {
        if($this->socket){
            $this->log('closing socket connection');
            $str = $this->serialize(self::OPTION_HEARTBEAT, 0, '');
            $this->write($str);
            socket_close($this->socket);
        }
        if($this->watcher){
            $this->log('stopping watcher');
            $this->watcher->stop();
            $this->watcher = null;
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
        socket_set_nonblock($this->socket);
        return $this;
    }
    
    public function log($message)
    {
        list($m1, ) = explode(' ', microtime());
        $date = date('Y-m-d H:i:s') . substr($m1, 1);
        echo $date."\t".$message."\n";
    }
    
    public function serialize(...$data)
    {
        $tmp = msgpack_pack($data);
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($response)
    {
        $ret = array();
        while($response){
            $arr = unpack('N', substr($response, 0, 4));
            $strlen = array_pop($arr);
            $ret[] = msgpack_unpack(substr($response, 4, $strlen));
            $response = substr($response, 4 + $strlen);
        }
        return $ret;
    }
    /**
     * Async Request
     * @param type $message
     * @param type $multi
     * @return boolean|string
     */
    public function request($message = '', $multi = 0)
    {
        $kind = self::OPTION_REQUEST;
        if($multi){
            $kind |= self::OPTION_MULTI;
        }
        $str = $this->serialize($kind, md5($message), $message);
        $this->write($str);
    }
    
    public function read()
    {
        $tmp = '';
        socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        $this->log("read , errorCode: $errorCode, error:".socket_strerror($errorCode));
        if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
            return '';
        }
        if( (0 === $errorCode && null === $tmp) ||EPIPE == $errorCode || ECONNRESET == $errorCode){
            $this->watcher->stop();
            socket_close($this->socket);
            $this->connect();
            $this->process();
            return false;
        }
        return $tmp;
    }
    
    public function write($str)
    {
        $num = socket_write($this->socket, $str, strlen($str));
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $this->watcher->stop();
            socket_close($this->socket);
            $this->connect();
            $this->process();
            return false;
        }
        $this->log("socket write len: ". json_encode($num) .", ". $str);
        return $num;
    }
    
    public function handle()
    {
        $response= '';
        while($str = $this->read()){
            $response .= $str;
        }
        if(empty($response)){
            return null;
        }
        $ret = $this->unserialize($response);
        $this->log(json_encode($ret));
        foreach($ret as $line){
            list($kind, $seqId, ) = $line;
            if(($kind & self::OPTION_REPLY) > 0){
                if(is_callable($this->consumer)){
                    call_user_func_array($this->consumer, $line);
                }
            }else if(($kind & self::OPTION_REQUEST) > 0){
                if(is_callable($this->worker)){
                    $reply = call_user_func_array($this->worker, $line);
                    $msg = msgpack_pack($reply);
                    $kind = self::OPTION_WORKER | self::OPTION_REPLY;
                    $str = $this->serialize($kind, $seqId, $msg);
                    $this->write($str);
                }
            }
        }
    }
    
    public function register()
    {
        $kind = self::OPTION_WORKER | self::OPTION_REQUEST;
        $str = $this->serialize($kind, 0, '');
        $this->write($str);
    }
    
    public function process()
    {
        $that = $this;
        $this->watcher = new EvIo($this->socket, Ev::WRITE, function ($w)use ($that){
            $w->stop();
            $that->register();
            $that->watcher = new EvIo($that->socket, Ev::READ, function() use ($that){
                $that->handle();
            });
        });
        Ev::run();
    }
    
    public function test($kind, $seqId, $message)
    {
        $this->log("receive: {$kind}\t{$seqId}\t".$message);
        return msgpack_unpack($message);
    }
}

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

$worker = new Worker();
$worker->connect();
$worker->process();