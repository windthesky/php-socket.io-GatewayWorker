<?php 

use Workerman\Worker;
use GatewayWorker\Register;

// 自动加载类
if(!defined('GLOBAL_START'))
{
    require_once __DIR__ . '/../../load_env.php';
}
require_once __DIR__ . '/../../vendor/autoload.php';

// register 必须是text协议
$register = new Register(env('REGISTER'));

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

