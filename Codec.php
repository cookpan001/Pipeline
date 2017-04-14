<?php

namespace cookpan001\Proxy;

interface Codec
{
    public function encode(...$data);
    public function serialize($data);
    public function unserialize($data);
}