<?php

namespace cookpan001\Pipeline\Reply;

class Again
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