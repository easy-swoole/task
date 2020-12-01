# Task组件
出于更加灵活定制化的考虑，EasySwoole实现了一个基于自定义进程的Task组件，用以实现异步任务，并解决：
- 无法投递闭包任务
- 无法在TaskWorker等其他自定义进程继续投递任务
- 实现任务限流与状态监控

> 该组件可以独立使用

## 安装 
```
composer require easyswoole/task
```

## 示例代码

```php
use EasySwoole\Task\MessageQueue;
use EasySwoole\Task\Task;

require 'vendor/autoload.php';


$task = new Task();
//如果需要任务队列，则默认设置进去一个Queue驱动
$queue = new MessageQueue();
$task->getConfig()->setTaskQueue($queue);

$http = new swoole_http_server("127.0.0.1", 9501);
/*
添加服务
*/
$task->attachToServer($http);

$http->on("request", function ($request, $response)use($task){
    if(isset($request->get['sync'])){
        $ret = $task->sync(function ($taskId,$workerIndex){
            return "{$taskId}.{$workerIndex}";
        });
        $response->end("sync result ".$ret);
    }else if(isset($request->get['status'])) {
        var_dump($task->status());
    }else{
        $id = $task->async(function ($taskId,$workerIndex){
            \co::sleep(1);
            var_dump("async id {$taskId} task run");
        });
        $response->end("async id {$id} ");
    }
});

$http->start();
```

## 任务投递类型
### 闭包函数
### 任务接口
### callable