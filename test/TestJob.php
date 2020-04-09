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