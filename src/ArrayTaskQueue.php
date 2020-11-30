<?php


namespace EasySwoole\Task;


use EasySwoole\Task\AbstractInterface\TaskQueueInterface;

class ArrayTaskQueue implements TaskQueueInterface
{
    private $queue = [];
    function pop(): ?Package
    {
        // TODO: Implement pop() method.
    }

    function push(Package $package, float $timeout = 3): bool
    {
        // TODO: Implement push() method.
    }
}