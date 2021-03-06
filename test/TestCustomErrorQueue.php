<?php


namespace Egret\Queue\Test;

use Egret\Queue\AbstractQueueCommand;

class TestCustomErrorQueue extends AbstractQueueCommand
{
    protected function configure()
    {
        parent::configure(); // TODO: Change the autogenerated stub
        $this->setName('error')->setDescription('设置自定义的错误处理');
        $this->driver = 'redis';
        $this->config = include CONSOLE_PATH . '/test/config/redis.php';
        $this->pidPath = CONSOLE_PATH . '/test/runtime';
        $this->errorFunc = function ($message, $job, $exception) {
            echo '窝草，报错了：' . $message . PHP_EOL;
        };
    }

    protected function getLogger()
    {

    }

    protected function getDingTalk()
    {

    }
}