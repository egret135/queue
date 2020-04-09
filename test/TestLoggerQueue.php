<?php


namespace Egret\Queue\Test;


use Egret\Queue\AbstractQueueCommand;
use Egret\Queue\DingTalk;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class TestLoggerQueue extends AbstractQueueCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('logger')->setDescription('测试monolog日志');
        $this->driver = 'redis';
        $this->config = include CONSOLE_PATH . '/test/config/redis.php';
        $this->pidPath = CONSOLE_PATH . '/test/runtime';
    }

    protected function getLogger()
    {
        $logger = new Logger('queue');
        $rotating = new RotatingFileHandler(CONSOLE_PATH . '/test/runtime/info.log', 30);
        $logger->pushHandler($rotating);
        return $logger;
    }

    protected function getDingTalk()
    {
        return null;
    }
}