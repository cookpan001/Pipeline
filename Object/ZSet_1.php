<?php

namespace cookpan001\Pipeline\Object;

use cookpan001\Pipeline\Reply\Error;

class ZSet
{
    public function __construct()
    {
        
    }
    
    public function zadd($database, $key, ...$args)
    {
        $flags = array();
        $tmp = array_shift($args);
        while($tmp && !ctype_digit($tmp)){
            $flags[] = $tmp;
            $tmp = array_shift($args);
        }
        if(!ctype_digit($tmp)){
            array_unshift($flags, $tmp);
        }else{
            array_unshift($args, $tmp);
        }
        if(count($args) % 2 != 0){
            return new Error('ERR wrong number of arguments for '.__FUNCTION__);
        }
        $count = 0;
        $i = 0;
        while($i < count($args)){
            $member = $args[$i + 1];
            $score = $args[$i];
            if(!isset($database->keys[$key][$member])){
                ++$count;
            }
            $database->keys[$key][$member] = $score;
            $i += 2;
        }
        return $count;
    }
    
    public function zrem($database, $key, ...$members)
    {
        $count = 0;
        foreach($members as $member){
            if(isset($database->keys[$key][$member])){
                unset($database->keys[$key][$member]);
                ++$count;
            }
        }
        return $count;
    }
    
    public function zmembers($database, $key)
    {
        $ret = array();
        if(!isset($database->keys[$key])){
            return $ret;
        }
        foreach($database->keys[$key] as $member => $_score){
            $ret[] = $member;
        }
        return $ret;
    }
    
    public function zdelay($database, $key)
    {
        $ret = array();
        if(!isset($database->delayed[$key])){
            return $ret;
        }
        foreach($database->delayed[$key] as $member){
            $ret[] = $member;
        }
        return $ret;
    }
    
    public function zcard($database, $key)
    {
        if(!isset($database->keys[$key])){
            return 0;
        }
        return count($database->keys[$key]);
    }
    
    public function zdelaycount($database, $key)
    {
        if(!isset($database->delayed[$key])){
            return 0;
        }
        return count($database->delayed[$key]);
    }
    
    public function zscore($database, $key, $member)
    {
        if(!isset($database->keys[$key][$member])){
            return null;
        }
        return $database->keys[$key][$member];
    }
}