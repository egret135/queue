<?php

namespace Egret\Queue;

use Closure;
use Monolog\Logger;
use Swoole\Process;
use Swoole\Process\Pool;

class QueueProcessPool
{
    protected $workerNum;
    /**
     * @var Closure 有两个参数，第一个是连接池对象$pool, 第二个是进程$id
     */
    protected $callback;
    /**
     * @var int 进程池主进程PID
     */
    protected $pid;
    /**
     * @var string pid文件夹路径
     */
    protected $pidPath;
    /**
     * @var string pid文件完整路径
     */
    protected $pidFile;
    /**
     * @var string 进程池名称
     */
    protected $poolName;
    /**
     * @var Logger
     */
    protected $logger;

    public function __construct($workerNum, Closure $callback, $poolName, $pidPath = '', $pidFile = '')
    {
        $this->workerNum = $workerNum;
        $this->callback = $callback;
        $this->pidPath = $pidPath;
        $this->poolName = $poolName;
        $this->pidFile = $pidFile;
    }

    /**
     * @param Logger $logger
     * @return QueueProcessPool
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function run($daemon = true)
    {
        $daemon &&Process::daemon();
        $pool = new Pool($this->workerNum);
        $pool->on('workerStart', $this->callback);
        $this->pid(getmypid());
        $pool->start();
    }

    protected function pid($pid)
    {
        if ($this->logger) {
            $this->logger->info("queue: {$this->poolName}，进程启动");
        }
        if (!$this->pidPath) {
            return ;
        }
        if (!file_exists($this->pidPath)) {
            mkdir($this->pidPath, 0777, true);
        }
        file_put_contents($this->pidFile, $pid);
    }
}