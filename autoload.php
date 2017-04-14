<?php

define('PROXY_ROOT', dirname(__FILE__));

class MyAutoload
{
    public $classMap = array(
        'cookpan001\\Proxy' => array(PROXY_ROOT),
    );
    
    public function __autoload($class_name)
    {
        foreach($this->classMap as $namespace => $tmp){
            if(0 !== strpos($class_name, $namespace)){
                continue;
            }
            $left = str_replace($namespace, '', $class_name);
            $dir = str_replace('\\', DIRECTORY_SEPARATOR, trim($left, '\\'));
            foreach($tmp as $path){
                $filepath = $path . DIRECTORY_SEPARATOR . $dir . '.php';
                if(file_exists($filepath)){
                    require $filepath;
                    return true;
                }
            }
        }
        return false;
    }
}

spl_autoload_register(array(new MyAutoload, '__autoload'));
