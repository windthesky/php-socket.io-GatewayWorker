<?php
/** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */

use Workerman\Worker;
//use \Workerman\WebServer;
//use GatewayWorker\Gateway;
use GatewayWorker\BusinessWorker;
//use Workerman\Autoloader;
use GlobalData\Client as GlobalData;

// 自动加载类
if(!defined('GLOBAL_START'))
{
    require_once __DIR__ . '/../../load_env.php';
}
require_once __DIR__ . '/../../vendor/autoload.php';


// bussinessWorker 进程
$worker = new BusinessWorker();
// worker名称
$worker->name = env('WORKER_NAME');
// bussinessWorker进程数量
$worker->count = intval(env('WORKER_COUNT'));
// 服务注册地址
$worker->registerAddress = env('REGISTER_ADDRESS');

if(env('USE_GLOBALDATA')){
    global $global_data;
    $global_data = new GlobalData(env('GLOBALDATA_ADDRESS'));
}

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

