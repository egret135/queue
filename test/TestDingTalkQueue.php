<?php


namespace Egret\Queue\Test;


use Egret\Queue\AbstractQueueCommand;
use Egret\Queue\DingTalk;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class TestDingTalkQueue extends AbstractQueueCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('dingtalk')->setDescription('测试钉钉报警');
        // 以predis为驱动
        $this->config = include CONSOLE_PATH . '/test/config/redis.php';
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
        $config = include CONSOLE_PATH . '/test/config/dingtalk.php';
        return new DingTalk($config['token'], $config['sign'], $config['secret']);
    }
}