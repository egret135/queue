<?php


namespace Egret\Queue;

abstract class AbstractJob
{
    protected $index = 1;

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return $this
     */
    public function incrIndex()
    {
        $this->index += 1;
        return $this;
    }

    abstract public function execute();
}