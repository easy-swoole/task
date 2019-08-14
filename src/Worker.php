<?php


namespace EasySwoole\Task;


use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Swoole\Atomic\Long;
use Swoole\Coroutine\Socket;
use Swoole\Table;

class Worker extends AbstractUnixProcess
{
    protected $processId;
    /**
     * @var Table
     */
    protected $infoTable;
    /** @var Long */
    protected $taskIdAtomic;
    /**
     * @var Config
     */
    protected $taskConfig;

    public function run($arg)
    {
        $this->processId = $arg['processId'];
        $this->infoTable = $arg['infoTable'];
        $this->taskIdAtomic = $arg['taskIdAtomic'];
        $this->taskConfig = $arg['taskConfig'];
        $this->infoTable->set($this->processId,[
            'running'=>0,
            'success'=>0,
            'fail'=>0
        ]);
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {
        // 收取包头4字节计算包长度 收不到4字节包头丢弃该包
        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $socket->sendAll(Protocol::pack(serialize(Task::ERROR_PACKAGE_ERROR)));
            $socket->close();
            return;
        }

        // 收包头声明的包长度 包长一致进入命令处理流程
        //多处close是为了快速释放连接
        $allLength = Protocol::packDataLength($header);
        $data = $socket->recvAll($allLength, 1);
        if (strlen($data) == $allLength) {
            /** @var Package $package */
            $package = unserialize($data);
            try{
                if($this->infoTable->incr($this->processId,'running',1) < $this->taskConfig->getMaxRunningNum()){
                    $taskId = $this->taskIdAtomic->add(1);
                    switch ($package->getType()){
                        case $package::ASYNC:{
                            $socket->sendAll(Protocol::pack(serialize($taskId)));
                            $this->runTask($package,$taskId);
                            $socket->close();
                            break;
                        }
                        case $package::SYNC:{
                            $reply = $this->runTask($package,$taskId);
                            $socket->sendAll(Protocol::pack(serialize($reply)));
                            $socket->close();
                            break;
                        }
                    }
                }else{
                    $socket->sendAll(Protocol::pack(serialize(Task::ERROR_PROCESS_BUSY)));
                    $socket->close();
                }
                $this->infoTable->incr($this->processId,'success',1);
            }catch (\Throwable $exception){
                $this->infoTable->incr($this->processId,'fail',1);
                if($package->getType() != $package::ASYNC){
                    /*
                     * 异步的已经立即返回了
                     */
                    $socket->sendAll(Protocol::pack(serialize(Task::ERROR_TASK_ERROR)));
                    $socket->close();
                }
                throw $exception;
            }finally{
                $this->infoTable->decr($this->processId,'running',1);
            }
        }else{
            $socket->sendAll(Protocol::pack(serialize(Task::ERROR_PACKAGE_ERROR)));
            $socket->close();
        }
    }

    protected function onException(\Throwable $throwable, ...$args)
    {
        if(is_callable($this->taskConfig->getOnException())){
            call_user_func($this->taskConfig->getOnException(),$throwable,$this->processId);
        }else{
            throw $throwable;
        }
    }

    protected function runTask(Package $package,int $taskId)
    {
        $task = $package->getTask();
        $reply = null;
        if(is_string($task) && class_exists($task)){
            $ref = new \ReflectionClass($task);
            if($ref->implementsInterface(TaskInterface::class)){
                /** @var TaskInterface $ins */
                $ins = $ref->newInstance();
                $reply = $ins->run();
            }
        }else if($task instanceof SuperClosure){
            $reply = $task($taskId);
        }else if(is_callable($task)){
            $reply = call_user_func($task,$taskId);
        }
        if(is_callable($package->getOnFinish())){
            $reply = call_user_func($package->getOnFinish(),$reply,$taskId);
        }
        return $reply;
    }
}