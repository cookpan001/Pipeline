<?php

namespace cookpan001\Pipeline\Codec;

use cookpan001\Pipeline\Codec;

class Redis implements Codec
{
    const END = "\r\n";
    
    public function encode(...$data)
    {
        
    }
    
    public function serialize($data)
    {
        if($data instanceof Reply\Error){
            return '-'.$data->getMessage().self::END;
        }
        if($data instanceof Reply\OK){
            return '+OK'.self::END;
        }
        if($data instanceof Reply\TimeoutException){
            return '*-1'.self::END;
        }
        if(is_int($data)){
            return ':'.$data.self::END;
        }
        if($data instanceof Reply\Bulk){
            return '$'.strlen($data->str).self::END.$data->str.self::END;
        }
        if(is_string($data)){
            return '+'.$data.self::END;
        }
        if(is_null($data)){
            return '$-1'.self::END;
        }
        $str = '*'.count($data).self::END;
        foreach($data as $line){
            if(is_null($line)){
                $str .= '$-1'.self::END;
            }else if(is_array($line)){
                $str .= self::serialize($line).self::END;
            }else{
                $str .= '$'.strlen($line).self::END.$line.self::END;
            }
        }
        return $str;
    }

    private static function parse($str)
    {
        return preg_split('#\s+#', $str);
    }
    
    public static function unserialize($str)
    {
        if(empty($str)){
            return array();
        }
        $pos = 0;
        $command = array();
        $len = strlen($str);
        while($pos < $len){
            if($str[$pos] != '*'){
                $position = strpos($str, self::END, $pos);
                if(false === $position){
                    $command[] = self::parse(substr($str, $pos));
                    $pos += strlen($str);
                    continue;
                }
                if($position != $pos){
                    $command[] = self::parse(substr($str, $pos, $position - $pos));
                    $pos += $position - $pos;
                }
                $pos += 2;
                continue;
            }
            ++$pos;
            $tmpCmd = array();
            $count = '';
            while($str[$pos] != "\r"){
                $count .= $str[$pos];
                ++$pos;
            }
            $pos += strlen(self::END);
            $count = intval($count);
            while($count){
                ++$pos;
                $strlen = '';
                while($str[$pos] != "\r"){
                    $strlen .= $str[$pos];
                    ++$pos;
                }
                $pos += strlen(self::END);
                $tmpCmd[] = substr($str, $pos, intval($strlen));
                $pos += $strlen + strlen(self::END);
                $count--;
            }
            $command[] = $tmpCmd;
        }
        return $command;
    }
}