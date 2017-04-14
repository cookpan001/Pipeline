<?php

namespace cookpan001\Pipeline;

interface Codec
{
    public function encode(...$data);
    public function serialize($data);
    public function unserialize($data);
}