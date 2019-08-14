<?php


namespace EasySwoole\Task;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use Swoole\Atomic\Long;
use Swoole\Server;
use Swoole\Table;

class Task
{
    private $taskIdAtomic;
    private $table;
    private $config;

    const ERROR_PROCESS_BUSY = -1;
    const ERROR_PACKAGE_ERROR = -2;
    const ERROR_TASK_ERROR = -3;

    function __construct(Config $config)
    {
        $this->taskIdAtomic = new Long(0);
        $this->table = new Table(512);
        $this->table->column('running',Table::TYPE_INT,4);
        $this->table->column('success',Table::TYPE_INT,4);
        $this->table->column('fail',Table::TYPE_INT,4);
        $this->table->create();
        $this->config = $config;
    }

    public function attachToServer(Server $server)
    {
        $list = $this->__initProcess();
        /** @var AbstractProcess $item */
        foreach ($list as $item){
            $server->addProcess($item->getProcess());
        }
    }

    public function __initProcess():array
    {
        $ret = [];
        for($i = 0;$i < $this->config->getWorkerNum();$i++){
            $config = new UnixProcessConfig();
            $config->setProcessName($this->config->getServerName().".TaskWorker");
            $config->setSocketFile($this->idToUnixName($i));
            $config->setArg([
                'processId'=>$i,
                'infoTable'=>$this->table,
                'taskIdAtomic'=>$this->taskIdAtomic,
                'taskConfig'=>$this->config
            ]);
            $ret[$i] = new Worker($config);
        }
        return  $ret;
    }

    public function async($task,callable $finishCallback = null,$taskWorkerId = null)
    {
        if($taskWorkerId === null){
            $id = $this->findOutFreeId();
        }else{
            $id = $taskWorkerId;
        }

        if($id !== null){
            if($task instanceof \Closure){
                try{
                    $task = new SuperClosure($task);
                }catch (\Throwable $throwable){
                    return false;
                }
            }
            $package = new Package();
            $package->setType($package::ASYNC);
            $package->setTask($task);
            $package->setOnFinish($finishCallback);
            return $this->sendAndRecv($package,$id);
        }else{
            return false;
        }
    }

    public function sync($task,$timeout = 3.0,$taskWorkerId = null)
    {
        if($taskWorkerId === null){
            $id = $this->findOutFreeId();
        }else{
            $id = $taskWorkerId;
        }
        if($id !== null){
            if($task instanceof \Closure){
                try{
                    $task = new SuperClosure($task);
                }catch (\Throwable $throwable){
                    return false;
                }
            }
            $package = new Package();
            $package->setType($package::SYNC);
            $package->setTask($task);
            return $this->sendAndRecv($package,$id,$timeout);
        }else{
            return false;
        }
    }

    function barrier(array $taskList,float $timeout = 3.0):array
    {
        $list = [];
    }

    /*
     * 找出空闲的进程编号,目前用随机
     */
    private function findOutFreeId():?int
    {
        mt_srand();
        return rand(0,$this->config->getWorkerNum() - 1);
    }

    private function idToUnixName(int $id):string
    {
        return $this->config->getTempDir()."/TaskWorker.".md5($this->config->getServerName())."{$id}.sock";
    }

    private function sendAndRecv(Package $package,int $id,float $timeout = null)
    {
        if($timeout === null){
            $timeout = $this->config->getTimeout();
        }
        $client = new UnixClient($this->idToUnixName($id));
        $client->send(Protocol::pack(serialize($package)));
        $ret = $client->recv($timeout);
        if (!empty($ret)) {
            return unserialize(Protocol::unpack($ret));
        }else{
            return null;
        }
    }
}