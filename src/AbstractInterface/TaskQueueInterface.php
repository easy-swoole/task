<?php


namespace EasySwoole\Task\AbstractInterface;


use EasySwoole\Task\Package;

interface TaskQueueInterface
{
    function pop():?Package;
    function push(Package $package,float $timeout = 3):bool ;
}