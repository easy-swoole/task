# task

```php

$config = new \EasySwoole\Task\Config();
$task = new EasySwoole\Task\Task($config);


$http = new swoole_http_server("127.0.0.1", 9501);

$task->attachToServer($http);

$http->on("request", function ($request, $response)use($task){
    $task->async(function (){
       \co::sleep(3);
       var_dump('task run');
    });
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World\n");

});

$http->start();

```