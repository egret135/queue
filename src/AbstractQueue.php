<?php

namespace Egret\Queue;

use Closure;
use Exception;
use Interop\Queue\Context;
use Interop\Queue\Topic;
use Monolog\Logger;

abstract class AbstractQueue
{
    /**
     * @var Context
     */
    protected $queue;
    /**
     * @var Topic
     */
    protected $topic;
    /**
     * @var int 默认超时时间30s
     */
    protected $timeout = 30000;
    /**
     * @var int 最大重试次数
     */
    protected $maxRetry = 3;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var DingTalk
     */
    protected $dingTalk;
    /**
     * @var string
     */
    protected $queueName;
    /**
     * @var int
     */
    protected $dingTraceNum;
    /**
     * @var string
     */
    protected $dingNoticeMobile;
    /**
     * @var Closure
     */
    protected $errorFunc;

    /**
     * 配置基本信息
     * @param $config
     * @return $this
     */
    abstract public function config($config);

    /**
     * AbstractQueue constructor.
     * @param $config
     * @param $topicName
     * @param int $timeout
     * @param int $maxRetry
     */
    public function __construct($config, $topicName, $timeout = 30000, $maxRetry = 3)
    {
        $this->config($config)->setTopic($topicName)->setTimeout($timeout);
    }

    /**
     * 设置队列topic
     * @param $topicName
     * @return $this
     */
    public function setTopic($topicName)
    {
        if ($topicName) {
            $this->topic = $this->queue->createQueue($topicName);;
        }
        return $this;
    }

    /**
     * 设置超时时间
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout = 30000)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 设置最大重试次数
     * @param int $maxRetry
     * @return $this
     */
    public function setMaxRetry($maxRetry = 3)
    {
        $this->maxRetry = $maxRetry;
        return $this;
    }

    /**
     * @param $logger
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param $errorFunc
     * @return AbstractQueue
     */
    public function setErrorFunc($errorFunc)
    {
        is_callable($errorFunc) && $this->errorFunc = $errorFunc;
        return $this;
    }

    /**
     * @param $dingTalk
     * @param $traceNum
     * @param $atMobiles
     * @return AbstractQueue
     */
    public function setDingTalk($dingTalk, $traceNum, $atMobiles)
    {
        $this->dingTalk = $dingTalk;
        $this->dingTraceNum = $traceNum;
        $this->dingNoticeMobile = $atMobiles;
        return $this;
    }

    /**
     * @param $queueName
     * @return $this
     */
    public function setQueueName($queueName)
    {
        $this->queueName = $queueName;
        return $this;
    }

    /**
     * 生产消息
     * @param AbstractJob $job
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws QueueException
     */
    public function produce(AbstractJob $job)
    {
        $this->checkTopic();
        $message = $this->queue->createMessage(serialize($job));
        $this->queue->createProducer()->send($this->topic, $message);
    }

    /**
     * 消费消息
     * @return mixed
     * @throws QueueException
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function consume()
    {
        $this->checkTopic();
        while (true) {
            $consumer = $this->queue->createConsumer($this->topic);
            $message = $consumer->receive($this->timeout);
            if (!is_object($message)) {
                continue;
            }
            $consumer->acknowledge($message);
            try {
                /**
                 * @var AbstractJob $job
                 */
                $job = unserialize($message->getBody());
                // 返回false，触发重试机制
                if ($job->execute() === false) {
                    if ($job->getIndex() < $this->maxRetry) {
                        $this->produce($job->incrIndex());
                    } else {
                        $this->error('任务重试次数已达上限', $job);
                    }
                }
            } catch (Exception $exception) {
                $this->error('任务运行报错', $job ?? null, $exception);
            }
        }
    }

    /**
     * 处理异常，日志记录及报警
     * @param $message
     * @param $job
     * @param null $exception
     */
    protected function error($message, $job, $exception = null)
    {
        if ($this->errorFunc) {
            call_user_func_array($this->errorFunc, [$message, $job, $exception]);
            return ;
        }

        if ($this->logger) {
            $this->logger->error($message, $this->exception($job, $exception));
        }
        if ($this->dingTalk) {
            $jobName = $job ? get_class($job) : '获取不到任务对象';
            $text = <<<STR
### 队列：$this->queueName

> 任务

$jobName

> 错误

$message

> 异常信息

{$this->getExceptionMsg($exception)}

> 异常信息

{$this->getTrace($exception)}

{$this->getAtMobiles()}
STR;
            $this->dingTalk->sendMarkdown($message, $text, explode(',', trim($this->dingNoticeMobile)));
        }
    }

    protected function exception($job, $exception)
    {
        if (!$exception) {
            return ['job' => $job ? get_class($job) : ''];
        }
        return [
            'job' => get_class($job),
            'error' => $exception->getMessage(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTraceAsString()
        ];
    }

    /**
     * 检查队列topic
     * @return bool
     * @throws QueueException
     */
    protected function checkTopic()
    {
        if (!$this->topic) {
            QueueException::throw('Topic Not Set!');
        }
        return true;
    }

    /**
     * @param $exception
     * @return string
     */
    protected function getExceptionMsg($exception)
    {
        return $exception instanceof Exception ? $exception->getMessage() : '无';
    }

    /**
     * @param $exception
     * @return array|string
     */
    protected function getTrace($exception)
    {
        if (!$exception instanceof Exception) {
            return '无';
        }
        $trace = $exception->getTraceAsString();
        $trace = explode(PHP_EOL, $trace);
        $trace = array_slice($trace, 0, intval($this->dingTraceNum) ?: 5);
        array_walk($trace, function (&$value) {
            $value .= '  ';
        });
        $trace = implode(PHP_EOL, $trace);
        return $trace;
    }

    /**
     * @return string
     */
    protected function getAtMobiles()
    {
        $at = '';
        $atMobiles = explode(',', trim($this->dingNoticeMobile));
        foreach ($atMobiles as $mobile) {
            $mobile && $at .= "@{$mobile} ";
        }
        return $at ? "> 通知人" . PHP_EOL . PHP_EOL . $at : '';
    }

}