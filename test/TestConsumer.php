<?php


namespace Egret\Queue\Test;

use Egret\Queue\KafkaQueue;
use Egret\Queue\RedisQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestConsumer extends Command
{
    protected $redisConf;
    protected $kafkaConf;

    protected function configure()
    {
        $this->setName('consumer')->setDescription('队列生产者');
        $this->addArgument('driver', InputArgument::REQUIRED, '队列驱动');
        $this->addArgument('topic', InputArgument::REQUIRED, 'topic');
        $this->redisConf = include CONSOLE_PATH . '/test/config/redis.php';
        $this->kafkaConf = include CONSOLE_PATH . '/test/config/kafka.php';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $input->getArgument('driver');
        $topic = $input->getArgument('topic');
        $job = new TestJob($this->getName(), false);
        switch ($driver) {
            case 'redis':
                (new RedisQueue($this->redisConf, $topic))->produce($job);
                break;
            case 'kafka':
                (new KafkaQueue($this->kafkaConf, $topic))->produce($job);
                break;
            default:
                $output->writeln(sprintf('Driver <info>%s</info> not exists...', $driver));
        }
        return 1;
    }
}