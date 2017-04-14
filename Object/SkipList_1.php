<?php

namespace cookpan001\Pipeline\Object;

class SkipList
{
    const MAX_LEVEL = 32;
    const MIN_LEVEL = 0;
    
    public $head = null;
    public $length = 0;
    public $dict = array();
    
    public function __construct()
    {
        $this->head = new SkipHead();
    }
    
    public function rand()
    {
        $k = 1;
        if(mt_rand(0, 1) % 2){
            ++$k;
        }
        return $k > self::MAX_LEVEL ? self::MAX_LEVEL : $k;
    }
    
    public function insert($score, $member)
    {
        $update = array();
        $width = array();
        $head = $this->head;
        for($i = self::MAX_LEVEL; $i >= self::MIN_LEVEL; ++$i){
            $pre = $head;
            $width[$i] = ($i == self::MAX_LEVEL ? 0 : $width[$i + 1]);
            while($pre->next($i) && ($score > $pre->next($i)->score || ($score == $pre->next($i)->score && $pre->next($i)->compare($member)))){
                $width[$i] += $pre->next($i)->span;
                $pre = $pre->next($i);
            }
            $update[$i] = $pre;
        }
        $randLevel = $this->rand();
        for($i = self::MAX_LEVEL; $i > $randLevel; --$i){
            $update[$i]->span = $this->length + 1;
        }
        $node = new SkipNode($score, $member);
        foreach ($update as $i => $preNode){
            $node->next[$i] = $preNode->next($i);
            $preNode->next[$i] = $node;
            
            $node->span = $preNode->span - ($width[0] - $width[$i]);
            $preNode->span = $width[0] - $width[$i] + 1;
        }
        foreach($width as $i => $span){
            $head->span[$i] = $span;
        }
        $this->length++;
        return $node;
    }
    
    public function delete($member)
    {
        if(!isset($this->dict[$member])){
            return 0;
        }
        $update = array();
        $width = array();
        $head = $this->head;
        $score = $this->dict[$member];
        for($i = self::MAX_LEVEL; $i >= self::MIN_LEVEL; ++$i){
            $pre = $head;
            $width[$i] = ($i == self::MAX_LEVEL ? 0 : $width[$i + 1]);
            while($pre->next($i) && 0 != $pre->next($i)->compare($member) ){
                $width[$i] += $pre->next($i)->span;
                $pre = $pre->next($i);
            }
            $update[$i] = $pre;
        }
        $randLevel = $this->rand();
        for($i = self::MAX_LEVEL; $i > $randLevel; --$i){
            $update[$i]->span = $this->length + 1;
        }
        $node = new SkipNode($score, $member);
        foreach ($update as $i => $preNode){
            if($preNode instanceof SkipNode && $preNode->member == $member){
                $preNode->member = $member;
                continue;
            }
            $node->next[$i] = $preNode->next($i);
            $preNode->next[$i] = $node;
            
            $node->span = $preNode->span - ($width[0] - $width[$i]);
            $preNode->span = $width[0] - $width[$i] + 1;
        }
        foreach($width as $i => $span){
            $head->span[$i] = $span;
        }
        $this->length++;
    }
    
    public function search()
    {
        
    }
}

class SkipHead
{
    public $next = null;
    public $span = 0;
    
    public function __construct()
    {
        for($i = 0; $i < SkipList::MAX_LEVEL; ++$i){
            $this->next[$i] = null;
        }
    }
    /**
     * @return SkipNode
     */
    public function next($level)
    {
        if(!isset($this->next[$level])){
            return null;
        }
        return $this->next[$level];
    }
}

class SkipNode
{
    public $next = null;
    public $previous = null;
    public $score = null;
    public $member = null;
    public $span = 0;
    
    public function __construct($score, $member)
    {
        $this->score = $score;
        $this->member = $member;
    }
    /**
     * @return SkipNode
     */
    public function next($level)
    {
        if(!isset($this->next[$level])){
            return null;
        }
        return $this->next[$level];
    }
    
    public function compare($member)
    {
        return 1;
    }
}