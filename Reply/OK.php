<?php

namespace cookpan001\Proxy\Reply;

class OK
{
    private static $obj = null;
    
    public static function instance()
    {
        if(is_null(self::$obj)){
            self::$obj = new self();
        }
        return self::$obj;
    }
}