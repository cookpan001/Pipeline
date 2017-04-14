<?php

namespace cookpan001\Pipeline\Reply;

class Error extends \Exception
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