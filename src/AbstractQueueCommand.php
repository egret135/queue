<?php


namespace Egret\Queue;

use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractQueueCommand extends Command
{
    /**
     * @var array 队列驱动配置
     */
    protected $config = [];
    /**
     * @var string 队列驱动
     */
    protected $driver = 'redis';
    /**
     * @var string 进程池主进程PID文件路径
     */
    protected $pidPath;
    /**
     * @var Closure 异常自定义回调方法
     */
    protected $errorFunc;

    abstract protected function getLogger();

    abstract protected function getDingTalk();

    protected function configure()
    {
        $this->setDescription('队列');
        $this->addArgument('status', InputArgument::REQUIRED, 'status/stop/start');
        $this->addArgument('topic', InputArgument::OPTIONAL, 'topic');
        $this->addOption('workerNum', 'w', InputArgument::OPTIONAL, '进程数量', 1);
        $this->addOption('maxRetry', 'r', InputArgument::OPTIONAL, '最大重试次数', 3);
        $this->addOption('timeout', 't', InputArgument::OPTIONAL, '单次等待超时时间，单位毫秒', 30000);
        $this->addOption('ding_trace_num', 'c', InputArgument::OPTIONAL, '队列异常钉钉通知展示调试信息数', 5);
        $this->addOption('ding_notice_mobile', 'm', InputArgument::OPTIONAL, '队列异常钉钉通知@人', '');
        $this->addOption('daemon', 'd', InputOption::VALUE_NONE, '以守护进程运行');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $status = $input->getArgument('status');
        switch ($status) {
            case 'start':
                $this->start($input, $output);
                break;
            case 'stop':
                $this->shutdown($input, $output);
                break;
//            case 'restart': // 启动进程需要参数，自动重启获取不到原来的参数，比较麻烦
//                $this->restart($input, $output);
//                break;
            case 'status':
            default:
                $this->status($input, $output);
                break;
        }

        return 0;
    }

    protected function getPidFile()
    {
        return $this->pidPath . '/queue-' . $this->getName() . '.pid';
    }

    protected function isRunning()
    {
        $pidFile = $this->getPidFile();
        if (file_exists($pidFile)) {
            return posix_kill(file_get_contents($pidFile), 0);
        }
        return process_running("queue {$this->getName()} start");
    }

    protected function start(InputInterface $input, OutputInterface $output)
    {
        if ($this->isRunning()) {
            $output->writeln(sprintf('Queue <info>%s</info> already running!', $this->getName()));
            return ;
        }
        $topic = $input->getArgument('topic');
        if (!$topic) {
            $output->writeln(sprintf('Queue <info>%s</info> topic not set!!!', $this->getName()));
            return ;
        }
        $options = $input->getOptions();
        $pool = new QueueProcessPool($options['workerNum'], function ($pool, $id) use ($topic, $options) {
            if ($logger = $this->getLogger()) {
                $logger->info("queue: {$this->getName()}，子进程#{$id}启动");
            }
            switch ($this->driver) {
                case 'kafka':
                    $queue = new KafkaQueue($this->config, $topic, $options['timeout'], $options['maxRetry']);
                    break;
                case 'redis':
                default:
                    $queue = new RedisQueue($this->config, $topic, $options['timeout'], $options['maxRetry']);
                    break;
            }
            $queue->setDingTalk($this->getDingTalk(), $options['ding_trace_num'], $options['ding_notice_mobile'])
                ->setMaxRetry($options['maxRetry'])
                ->setLogger($this->getLogger())
                ->setQueueName($this->getName())
                ->setErrorFunc($this->errorFunc)
                ->consume();
        }, $this->getName(), $this->pidPath, $this->getPidFile());
        $pool->setLogger($this->getLogger())->run($options['daemon']);
    }

    protected function shutdown(InputInterface $input, OutputInterface $output)
    {
        if (!$this->isRunning()) {
            $output->writeln(sprintf('Queue <info>%s</info> not running!', $this->getName()));
            return ;
        }

        $pid = (int) @file_get_contents($this->getPidFile());
        if (kill_process($pid, SIGTERM)) {
            unlink($this->getPidFile());
        }

        $output->writeln(sprintf('Server <info>%s</info> [<info>#%s</info>] is shutdown...', $this->getName(), $pid));
        $output->writeln(sprintf('PID file %s is unlink', $this->getPidFile()), OutputInterface::VERBOSITY_DEBUG);
    }

    protected function restart(InputInterface $input, OutputInterface $output)
    {
        $this->shutdown($input, $output);
        $this->start($input, $output);
    }

    protected function status(InputInterface $input, OutputInterface $output)
    {
        if (!$this->isRunning()) {
            $output->writeln(sprintf('Queue <info>%s</info> not running!', $this->getName()));
            return ;
        }

        exec("ps axu | grep 'queue {$this->getName()} start' | grep -v grep", $list);

        // list all process
        $list = array_map(function ($v) {
            $status = preg_split('/\s+/', $v);
            unset($status[2], $status[3], $status[4], $status[6], $status[9]); //
            $status = array_values($status);
            $status[5] = $status[5] . ' ' . implode(' ', array_slice($status, 6));
            return array_slice($status, 0, 6);
        }, $list);

        // combine
        $headers = ['USER', 'PID', 'RSS', 'STAT', 'START', 'COMMAND'];
        foreach ($list as $key => $value) {
            $list[$key] = array_combine($headers, $value);
        }

        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($list)
        ;

        $output->writeln(sprintf("Queue: <info>%s</info>", $this->getName()));
        $output->writeln(sprintf(
            "PID file: <info>%s</info>, PID: <info>%s</info>",
            $this->getPidFile(),
            (int) @file_get_contents($this->getPidFile())) . PHP_EOL
        );
        $table->render();

        unset($table, $headers, $output);
    }
}