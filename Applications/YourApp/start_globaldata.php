<?php
use Workerman\Worker;
use GlobalData\Server;
// 自动加载类
if(!defined('GLOBAL_START'))
{
    require_once __DIR__ . '/../../load_env.php';
}

require_once __DIR__ . '/../../vendor/autoload.php';

if(env('USE_GLOBALDATA')){
    $worker = new Server(env('GLOBALDATA_HOST'), intval(env('GLOBALDATA_PORT')));
}else{
    $worker = new Worker();
}

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
