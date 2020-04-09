<?php

use Egret\Queue\Test\TestConsumer;
use Egret\Queue\Test\TestCustomErrorQueue;
use Egret\Queue\Test\TestDingTalkQueue;
use Egret\Queue\Test\TestKafkaQueue;
use Egret\Queue\Test\TestLoggerQueue;
use Egret\Queue\Test\TestRedisQueue;

return [
    TestDingTalkQueue::class,
    TestKafkaQueue::class,
    TestLoggerQueue::class,
    TestRedisQueue::class,
    TestConsumer::class,
    TestCustomErrorQueue::class,

];