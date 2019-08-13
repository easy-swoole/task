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
    private $attach = false;

    function __construct(Config $config)
    {
        $this->taskIdAtomic = new Long(0);
        $this->table = new Table(512);
        $this->table->column('runningNum',Table::TYPE_INT,4);
        $this->table->create();
        $this->config = $config;
    }

    public function attachToServer(Server $server)
    {
        $this->attach = true;
        $list = $this->__initProcess();
        /** @var AbstractProcess $item */
        foreach ($list as $item){
            $server->addProcess($item->getProcess());
        }
    }

    public function __initProcess():array
    {
        $ret = [];
        for($i = 1;$i <= $this->config->getWorkerNum();$i++){
            $config = new UnixProcessConfig();
            $config->setProcessName($this->config->getServerName().".TaskWorker");
            $config->setSocketFile($this->idToUnixName($i));
            $ret[$i] = new Worker($config);
        }
        return  $ret;
    }

    public function async($task,$finishCallback = null,$taskWorkerId = -1)
    {
        $id = $this->findOutFreeId();
    }

    public function sync($task,$timeout = 0.5,$taskWorkerId = -1)
    {

    }

    function barrier(array $taskList,$timeout = 0.5):array
    {

    }

    /*
     * 找出空闲的进程编号
     */
    private function findOutFreeId():?int
    {
        $list = [];
        foreach ($this->table as $key => $item){
            if($item['runningNum'] < $this->config->getMaxRunningNum()){
                $list[] = $key;
            }
        }
        if(!empty($list)){
            return $list[array_rand($list)];
        }else{
            return null;
        }
    }

    private function idToUnixName(int $id):string
    {
        return $this->config->getTempDir()."/TaskWorker.".md5($this->config->getServerName())."{$id}.sock";
    }
}