<?php


namespace EasySwoole\Task\AbstractInterface;


interface TaskInterface
{
    function run(int $taskId);
}