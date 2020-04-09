# queue
PHP的一个队列包，目前已有kafka和redis两种队列驱动，实现方式是用swoole的进程池 + symfony的console + enqueue的队列包

kafka队列使用需要先安装 rdkafka 拓展 

redis队列的使用需要有一个redis的操作包，这里指定了predis

后续可能会继续拓展新的队列驱动，目前只存在这两种

### 版本要求

```php
"php": "^7.1.3",
"ext-swoole": "^2.0 || ^3.0 || ^4.0",
```

### 安装教程

建议在composer.json里加上

```json
{
    "require": {
        xxxxxx,
        "egret/queue": "^1.0"
    },
    "config": {
        "bin-dir": "bin"
    }
}
```

```shell script
composer update egret/queue
```

当然也可以直接composer require，但是这样可执行文件就不会放到跟composer.json同级的bin目录下，比较难找

```shell script
composer require egret/queue
```

### 使用指引

创建一个简单的redis队列

```php
<?php

namespace Egret\Queue\Test;

use Egret\Queue\AbstractQueueCommand;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Egret\Queue\DingTalk;

// 继承这个抽象类
class TestRedisQueue extends AbstractQueueCommand
{
    protected function configure()
    {
        // 调用父类的configure方法，必须要有
        parent::configure();
        // 设置队列名称，必须要有，设置队列描述（非必须）
        $this->setName('redis')->setDescription('测试redis队列');
        // 设置队列驱动，不设置默认redis，目前只支持redis/kafka
        $this->driver = 'redis';
        // 设置redis连接的配置，这里测试时用，相关的配置信息往下看
        $this->config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '123456',
            'database' => 10,
            'predis_options' => [
                // 指定缓存的前缀
                'prefix'  => 'queue:test:'
            ]
        ];
        // 设置pid文件的存放路径，可以不设置，不设置不能用命令停止队列，这里配置的是我测试的路径，大家按自己的情况设置值
        $this->pidPath = CONSOLE_PATH . '/test/runtime';
    }

    // 设置Monolog的日志，设置之后会写入任务的相关日志，可不设置
    protected function getLogger()
    {
        $logger = new Logger('queue');
        $rotating = new RotatingFileHandler(CONSOLE_PATH . '/test/runtime/info.log', 30);
        $logger->pushHandler($rotating);
        return $logger;
    }

    // 配置钉钉机器人，任务异常时会报警到钉钉上，可不设置
    protected function getDingTalk()
    {
        $config = [
            'token' => 'xxxx',
            'secret' => 'xxxx',
            'sign' => true
        ];
        return new DingTalk($config['token'], $config['sign'], $config['secret']);
    }
}
```

启动队列命令

```shell script
./queue 队列别名 start topic名称(redis的键/kafka的topic)

Options:
  -w, --workerNum=WORKERNUM                    进程数量 [default: 1]
  -r, --maxRetry=MAXRETRY                      任务失败最大重试次数 [default: 3]
  -t, --timeout=TIMEOUT                        单次等待超时时间，单位毫秒 [default: 30000]
  -c, --ding_trace_num=DING_TRACE_NUM          队列异常钉钉通知展示调试信息数，一般可以不用修改 [default: 5]
  -m, --ding_notice_mobile=DING_NOTICE_MOBILE  队列异常钉钉通知@人，要用手机号，多个用,隔开，格式是13400000000,13500000000： [default: ""]
  -d, --daemon                                 以守护进程运行

启动上面的队列，指定topic为test

./queue redis start test
```

查看队列

```shell script
./queue redis status

Queue: redis
PID file: /queue-redis.pid, PID: 0

+-----------+-------+-------+------+--------+------------------------------+
| USER      | PID   | RSS   | STAT | START  | COMMAND                      |
+-----------+-------+-------+------+--------+------------------------------+
| zbangtang | 11993 | 6720  | S+   | 3:28PM | php ./queue redis start test |
| zbangtang | 11984 | 16056 | S+   | 3:28PM | php ./queue redis start test |
+-----------+-------+-------+------+--------+------------------------------+
```

停止队列，需要有PID文件才可以

```shell script
./queue redis stop
```

上面就完成了一个消费者队列的创建

下面展示一下生产者，先创建一个工作类，所有工作类都必须继承AbstractJob

```php
<?php


namespace Egret\Queue\Test;

use Egret\Queue\AbstractJob;

class TestJob extends AbstractJob
{
    protected $name;
    protected $flag;

    public function __construct($name, $flag = true)
    {
        $this->name = $name;
        $this->flag = $flag;
    }

    public function execute()
    {
        echo sprintf('queue %s is run' . PHP_EOL, $this->name);
        return $this->flag;
    }
}
```

Redis生产者代码

```php
$job = new TestJob($this->getName(), false);
(new RedisQueue($redisConf, $topic))->produce($job);
```

Kafka生产者代码

```php
$job = new TestJob($this->getName(), false);
$kafkaConf = [
     'global' => [
         'group.id' => 'test-group',
         'metadata.broker.list' => '127.0.0.1:9092',
         'enable.auto.commit' => 'false',
     ],
     'topic' => [
         'auto.offset.reset' => 'latest',
     ],
];
(new KafkaQueue($kafkaConf, $topic))->produce($job);
```

到这里就完成了队列的生产者和消费者的创建了，还差一个步骤就是把消费者加入到queue命令行中，用命令便可以启动和管理

此处采用文件加载的方式，使用的时候可以在composer.json同级的目录，或者同级的app或者application亦或者src目录下创建console.php，内容示例如下：

```php
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
```

必须把console.php存放到指定的几个地方之一，不然读取不到，文件存放位置示例：

```shell script
-- 项目demo  
　　-- app文件夹  
　　　　-- 可在app文件夹下创建console.php  
　　-- application文件夹   
　　　　-- 可在application文件夹下创建console.php  
　　-- src文件夹   
　　　　-- 可在src文件夹下创建console.php  
　　-- vendor  
　　-- 可在这一层目录下创建console.php  
　　-- composer.json  
　　-- composer.lock
```

创建完后执行./queue便可以看到相关的队列了

```shell script
./queue

________                               
\_____  \  __ __   ____  __ __   ____  
 /  / \  \|  |  \_/ __ \|  |  \_/ __ \ 
/   \_/.  \  |  /\  ___/|  |  /\  ___/ 
\_____\ \_/____/  \___  >____/  \___  >
       \__>           \/            \/ 
                                            v1.0.0                                                                   

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  consumer  队列生产者
  dingtalk  测试钉钉报警
  error     设置自定义的错误处理
  help      Displays help for a command
  kafka     测试kafka队列
  list      Lists commands
  logger    测试monolog日志
  redis     测试redis队列
```