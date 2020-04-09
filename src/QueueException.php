<?php

namespace Egret\Queue;

use Exception;

class QueueException extends Exception
{
    public static function throw($msg)
    {
        throw new static($msg);
    }
}