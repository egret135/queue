<?php


namespace Egret\Queue;

use Enqueue\RdKafka\RdKafkaConnectionFactory;

class KafkaQueue extends AbstractQueue
{
    /**
     * @inheritDoc
     */
    public function config($config)
    {
        $connFactory = new RdKafkaConnectionFactory($config);
        $this->queue = $connFactory->createContext();
        return $this;
    }
}