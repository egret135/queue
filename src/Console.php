<?php


namespace Egret\Queue;

use Symfony\Component\Console\Application as Symfony;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Console extends Symfony
{
    public $version = 'v1.0.0';

    public function __construct($class)
    {
        parent::__construct(<<<EOF
________                               
\_____  \  __ __   ____  __ __   ____  
 /  / \  \|  |  \_/ __ \|  |  \_/ __ \ 
/   \_/.  \  |  /\  ___/|  |  /\  ___/ 
\_____\ \_/____/  \___  >____/  \___  >
       \__>           \/            \/ 
                                            <info>{$this->version}</info>                                                                   
EOF
        );

        $this->registerCommands($class);
    }

    public function registerCommands($class)
    {
        foreach ($class as $item) {
            if (class_exists($item)) {
                $this->add(new $item());
            }
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws \Throwable
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        parent::doRun($input, $output);
    }
}