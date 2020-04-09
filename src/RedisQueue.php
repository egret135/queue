<?php


namespace Egret\Queue;

use Enqueue\Redis\RedisConnectionFactory;
use Enqueue\Redis\RedisContext;

class RedisQueue extends AbstractQueue
{
    /**
     * @inheritDoc
     */
    public function config($config)
    {
        $factory = new RedisConnectionFactory(array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'scheme_extensions' => ['predis'],
        ], $config));
        $this->queue = $factory->createContext();
        return $this;
    }
}