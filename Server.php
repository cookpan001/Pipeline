<?php

namespace cookpan001\Proxy;

class Server
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
    
    const FRAME_SIZE = 1500;
    
    /**
     * 服务器Socket
     * @var resource 
     */
    public $socket = null;
    public $host = '0.0.0.0';
    public $port = 6379;
    public $interval = 900;
    public $path = './dump.file';
    public $logPath = __DIR__ . DIRECTORY_SEPARATOR;
    public $service = 'proxy';
    public $terminate = 0;
    
    public $serverWatcher = null;
    /**
     * 客户端列表
     * @var array
     */
    public $client = array();
    public $defaultLoop = null;
    public $socketLoop = null;
    /**
     * 客户端
     * @var array 
     */
    public $connections = array();
    /**
     * 服务端 
     * @var array 
     */
    public $server = array();
    /**
     * 请求与响应映射
     * @var array
     */
    public $sequences = array();
    /**
     * 预处理
     */
    public $handler = null;//hook
    /**
     * 处理服务器状态
     */
    public $daemon = null;
    /**
     * 服务器启动时间
     * @var int
     */
    public $uptime = null;
    public $allConnections = 0;
    public $allCmds = 0;
    /**
     * 上次写回磁盘后添加更新数
     * @var type 
     */
    public $newUpdate = 0;
    /**
     * @var Codec
     */
    public $codec = null;
    
    public $watchers = array();

    public function __construct()
    {
        $this->daemonize();
        $this->setParam();
        $this->initStream();
        $this->uptime = time();
        $this->loop();
    }
    
    public function __destruct()
    {
        if($this->socket){
            socket_close($this->socket);
        }
        foreach($this->connections as $conn){
            $conn->_close();
        }
    }
    
    public function setParam()
    {
        global $argc, $argv;
        if($argc < 3){
            $this->codec = new Codec\MessagePack();
            return;
        }
        $config = parse_ini_file($argv[1], true);
        $index = $argv[2];
        $this->port = $config[$index]['port'];
        $this->service = $config[$index]['service'];
        if(isset($config[$index]['host'])){
            $this->host = $config[$index]['host'];
        }
        if(isset($config[$index]['interval'])){
            $this->interval = $config[$index]['interval'];
        }
        if(isset($config[$index]['path'])){
            $this->path = $config[$index]['path'];
        }
        if(isset($config[$index]['log_path']) && $config[$index]['log_path']){
            $this->logPath = $config[$index]['log_path'] . DIRECTORY_SEPARATOR;
        }
        if(isset($config[$index]['codec']) && $config[$index]['codec']){
            $codec = $config[$index]['codec'];
            $className = "Codec\{$codec}";
            if(class_exists($className)){
                $this->codec = new $className;
            }else{
                $this->codec = new Codec\MessagePack();
            }
        }else{
            $this->codec = new Codec\MessagePack();
        }
    }
    /**
     * 生成服务器的socket
     */
    public function create()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!$socket){
            $this->log("Unable to create socket");
            exit(1);
        }
        if(!socket_bind($socket, $this->host, $this->port)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        if(!socket_listen($socket)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        socket_set_nonblock($socket);
        $this->socket = $socket;
    }
    
    public function loop()
    {
        $this->defaultLoop = \EvLoop::defaultLoop();
        if (\Ev::supportedBackends() & ~\Ev::recommendedBackends() & \Ev::BACKEND_KQUEUE) {
            if(PHP_OS != 'Darwin'){
                $this->socketLoop = new \EvLoop(\Ev::BACKEND_KQUEUE);
            }
        }
        if (!$this->socketLoop) {
            $this->socketLoop = $this->defaultLoop;
        }
    }
    /**
     * 生成守护进程
     */
    public function daemonize()
    {
        umask(0); //把文件掩码清0  
        if (pcntl_fork() != 0){ //是父进程，父进程退出  
            exit();  
        }  
        posix_setsid();//设置新会话组长，脱离终端  
        if (pcntl_fork() != 0){ //是第一子进程，结束第一子进程     
            exit();  
        }
    }
    
    public function initStream()
    {
        global $STDIN, $STDOUT, $STDERR;
        fclose(STDIN);  
        fclose(STDOUT);  
        fclose(STDERR);
        $filename = $this->logPath. "{$this->service}.log";
        $this->output = fopen($filename, 'a');
        $this->errorHandle = fopen($this->logPath . "{$this->service}.error", 'a');
        $STDIN  = fopen('/dev/null', 'r'); // STDIN
        $STDOUT = $this->output; // STDOUT
        $STDERR = $this->errorHandle; // STDERR
        $this->installSignal();
        if (function_exists('gc_enable')){
            gc_enable();
        }
        register_shutdown_function(array($this, 'fatalHandler'));
        set_error_handler(array($this, 'errorHandler'));
    }
    
    public function stop()
    {
        \Ev::stop();
    }
    
    public function restart()
    {
        global $argv;
        $cmd = 'php '.__FILE__ . implode(' ', $argv);
        exec($cmd);
    }
    
    public function installSignal()
    {
        $this->signalWatcher[] = new \EvSignal(SIGTERM, array($this, 'signalHandler'));
        $this->signalWatcher[] = new \EvSignal(SIGUSR2, array($this, 'signalHandler'));
    }
    
    public function signalHandler($w)
    {
        $this->log(json_encode(array(SIGTERM, SIGUSR2, $w->signum)));
        switch ($w->signum) {
            case SIGTERM:
                $this->terminate = 1;
                $this->stop();
                break;
            case SIGUSR2:
                $this->terminate = 1;
                $this->stop();
                $this->restart();
                break;
            default:
                break;
        }
    }
    
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, var_export($errcontext, true));
        $this->error($str);
    }
    
    public function fatalHandler()
    {
        if($this->terminate){
            return;
        }
        $error = error_get_last();
        $this->error(var_export($error, true));
    }
    
    public function date()
    {
        list($m1, ) = explode(' ', microtime());
        return $date = date('Y-m-d H:i:s') . substr($m1, 1);
    }
    /**
     * 写日志
     */
    public function log($message)
    {
        $date = $this->date();
        if(is_array($message)){
            $str = $date."\t". json_encode($message)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDOUT;
        fwrite($STDOUT, $str);
    }
    
    public function error($message)
    {
        $date = $this->date();
        if(is_array($message)){
            $str = $date."\t". json_encode($message)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDERR;
        fwrite($STDERR, $str);
    }
    
    public function getConnectionType($id)
    {
        if(isset($this->server[$id])){
            return 'server('.$id.')';
        }else if(isset($this->client[$id])){
            return 'client('.$id.')';
        }
        return 'unknown('.$id.')';
    }
    /**
     * 读取连接中发来的数据
     * @return boolean|string
     */
    public function read($id)
    {
        $conn = $this->connections[$id];
        $tmp = '';
        $str = '';
        $i = 0;
        while(true){
            ++$i;
            //$this->log('step: '.$i);
            $num = socket_recv($conn->clientSocket, $tmp, self::FRAME_SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $str .= $tmp;
            }
            $errorCode = socket_last_error($conn->clientSocket);
            socket_clear_error($conn->clientSocket);
            $this->log("error:".socket_strerror($errorCode) . (', num=='. var_export($num, true)) . (', tmp=='. var_export($tmp, true)));
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                break;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                if(isset($this->server[$conn->id])){
                    $this->log('server '.$conn->id.' closed.');
                    unset($this->server[$conn->id]);
                }
                if(isset($this->client[$conn->id])){
                    $this->log('client '.$conn->id.' closed.');
                    unset($this->client[$conn->id]);
                }
                if(isset($this->connections[$conn->id])){
                    unset($this->connections[$conn->id]);
                }
                $conn->_close();
                return false;
            }
            if(0 === $num){
                $this->log('all data readed');
                break;
            }
        }
        $this->log('receive message: '.$str);
        return $str;
    }
    
    public function receive($id)
    {
        $conn = $this->connections[$id];
        $tmp = '';
        socket_recv($conn->clientSocket, $tmp, 1500, MSG_DONTWAIT);
        $errorCode = socket_last_error($conn->clientSocket);
        socket_clear_error($conn->clientSocket);
        $this->log("read from connection: ". $this->getConnectionType($conn->id).", error:".socket_strerror($errorCode));
        if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
            return '';
        }
        if( (0 === $errorCode && null === $tmp) ||EPIPE == $errorCode || ECONNRESET == $errorCode){
            if(isset($this->server[$conn->id])){
                $this->log('server '.$conn->id.' closed.');
                unset($this->server[$conn->id]);
            }
            if(isset($this->client[$conn->id])){
                $this->log('client '.$conn->id.' closed.');
                unset($this->client[$conn->id]);
            }
            if(isset($this->connections[$conn->id])){
                unset($this->connections[$conn->id]);
            }
            $conn->_close();
            $this->log('server: ' . implode(',', array_keys($this->server)));
            $this->log('client: ' . implode(',', array_keys($this->client)));
            return false;
        }
        $this->log('read message: '.$tmp);
        return $tmp;
    }
    /**
     * 向连接中写入数据
     * @return boolean
     */
    public function write(Connection $conn, $str)
    {
        $num = socket_write($conn->clientSocket, $str, strlen($str));
        $errorCode = socket_last_error($conn->clientSocket);
        socket_clear_error($conn->clientSocket);
        $this->log("write len: ".json_encode($num).", ". socket_strerror($errorCode).", ". $str);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $conn->_close();
            if(isset($this->server[$conn->id])){
                unset($this->server[$conn->id]);
            }
            if(isset($this->client[$conn->id])){
                unset($this->client[$conn->id]);
            }
            if(isset($this->connections[$conn->id])){
                unset($this->connections[$conn->id]);
            }
            return false;
        }
        return $num;
    }
    
    public function handleRegister($kind, $conn)
    {
        if(($kind & self::OPTION_WORKER)){//as server
            if(!isset($this->server[$conn->id])){
                $this->server[$conn->id] = $conn;
            }
        }else{//as client
            if(!isset($this->client[$conn->id])){
                $this->client[$conn->id] = $conn;
            }
        }
        $this->log('register');
    }
    
    public function handleRequest($kind, Connection $conn, $message)
    {
        if(($kind & self::OPTION_REQUEST) == 0){
            return;
        }
        if(empty($message)){
            return;
        }
        $this->log(json_encode(msgpack_unpack($message)));
        $md5 = md5($message);
        $this->sequences[$md5] = $conn;//等待的客户端
        if(($kind & self::OPTION_MULTI)){//群发
            $reply = $this->pushMulti($kind, $md5, $message);
        }else{//单发
            $reply = $this->push($kind, $md5, $message);
        }
        $nullReply = array(self::OPTION_REPLY, $md5, null);
        if(0 === $reply){
            $this->reply($conn, $nullReply);
            unset($this->sequences[$md5]);
            return;
        }else if(is_string($reply)){
            $this->reply($conn, $reply);
            unset($this->sequences[$md5]);
            return;
        }
        $this->log('request');
        $that = $this;
        $watcher = new \EvTimer(1, function() use ($that, $watcher, $md5, $nullReply){
            $watcher->stop();
            if(isset($that->sequences[$md5])){
                $that->reply($that->sequences[$md5], $nullReply);
                unset($that->sequences[$md5]);
            }
            unset($that->watchers[$md5]);
        });
        $this->log('handleRequest Timer');
        $this->watchers[$md5] = $watcher;
    }
    
    public function handleReply($kind, $seqId, $message)
    {
        if(($kind & self::OPTION_REPLY) == 0){
            return Reply\NoReply::instance();
        }
        $this->log('reply');
        if(isset($this->sequences[$seqId])){
            $this->reply($this->sequences[$seqId], array($kind, $seqId, $message));
            unset($this->sequences[$seqId]);
            //$reply = Reply\NoReply::instance();
        }
        if(isset($this->watchers[$seqId])){
            $this->watchers[$seqId]->stop();
            unset($this->watchers[$seqId]);
        }
    }
    /**
     * 处理连接中发来的指令
     */
    public function handle($id, $commands)
    {
        $conn = $this->connections[$id];
        foreach($commands as $arr){
            if(empty($arr) || !is_array($arr)){
                $this->log("wrong message: ". json_encode($arr));
                continue;
            }
            ++$this->allCmds;
            $this->log("incomming message: ". implode(',', $arr));
            list($kind, $seqId, $message) = $arr;
            $this->handleRegister($kind, $conn);
            $this->handleRequest($kind, $conn, $message);
            $this->handleReply($kind, $seqId, $message);
        }
    }
    /**
     * 返回消息
     */
    public function reply($conn, $message)
    {
        if(is_array($message)){
            $data = $this->codec->serialize($message);
        }else{
            $data = $message;
        }
        $this->write($conn, $data);
    }
    /**
     * 推送消息给服务端
     */
    public function push($kind, $seqId, $message)
    {
        $data = $this->codec->encode($kind, $seqId, $message);
        while(count($this->server)){
            if(count($this->server) > 1){
                $i = array_rand($this->server);
            }else{
                $i = key($this->server);
            }
            $connection = $this->server[$i];
            $ret = $this->write($connection, $data);
            if(false === $ret){
                continue;
            }
            $tmp = $this->read($connection->id);
            if (false === $tmp) {
                continue;
            }
            if(is_string($tmp) && strlen($tmp)){
                return $tmp;
            }
            return 1;
        }
        $this->log('push no worker');
        return 0;
    }
    
    public function pushMulti($kind, $seqId, $message)
    {
        $data = $this->codec->encode($kind, $seqId, $message);
        $count = 0;
        foreach($this->server as $connection){
            $ret = $this->write($connection, $data);
            if(false === $ret){
                continue;
            }
            $tmp = $this->read($connection->id);
            if (false === $tmp) {
                continue;
            }
            ++$count;
        }
        if($count == 0){
            $this->log('pushMulti no worker');
        }
        return $count;
    }
    /**
     * 开始监听
     */
    public function start()
    {
        $socket = $this->socket;
        $that = $this;
        $this->serverWatcher = new \EvIo($this->socket, \Ev::READ, function () use ($that, $socket){
            $clientSocket = socket_accept($socket);
            $that->process($clientSocket);
            ++$that->allConnections;
        });
        \Ev::run();
    }
    
    /**
     * 处理到来的新连接
     */
    public function process($clientSocket)
    {
        socket_set_nonblock($clientSocket);
        $conn = new Connection($clientSocket);
        $that = $this;
        $id = uniqid();
        $watcher = new \EvIo($clientSocket, \Ev::READ, function() use ($that, $id){
            $that->log('----------------HANDLE----------------');
            $str = $that->read($id);
            if(false !== $str){
                $commands = $that->codec->unserialize($str);
                $that->handle($id, $commands);
            }
            $that->log('----------------HANDLE FINISH---------');
        });
        $conn->_setId($id);
        $conn->_setWatcher($watcher);
        $this->connections[$id] = $conn;
        $this->socketLoop->run();
        //$this->log('connection '.$conn->id);
        \Ev::run();
    }
}

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

$server = new Server();
$server->create();
$server->start();