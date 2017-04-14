<?php

namespace cookpan001\Proxy\Codec;

use cookpan001\Proxy\Codec;

class MessagePack implements Codec
{
    public function serialize($data)
    {
        $tmp = msgpack_pack($data);
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($data)
    {
        $ret = array();
        while(strlen($data)){
            $arr = unpack('N', substr($data, 0, 4));
            $strlen = array_pop($arr);
            $ret[] = msgpack_unpack(substr($data, 4, $strlen));
            $data = substr($data, 4 + $strlen);
        }
        return $ret;
    }
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
}