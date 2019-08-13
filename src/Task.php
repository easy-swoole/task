<?php


namespace EasySwoole\Task;


use Swoole\Atomic\Long;

class Task
{
    private $taskIdAtomic;

    function __construct()
    {
        $this->taskIdAtomic = new Long(0);
    }


    function async($task,$finishCallback = null,$taskWorkerId = -1)
    {

    }

    public function sync($task,$timeout = 0.5,$taskWorkerId = -1)
    {

    }

    function barrier(array $taskList,$timeout = 0.5):array
    {

    }





}