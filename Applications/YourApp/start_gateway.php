<?php
/** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */

use GlobalData\Client as GlobalData;
use Workerman\Worker;
use GatewayWorker\Gateway;


// 自动加载类
if(!defined('GLOBAL_START'))
{
    require_once __DIR__ . '/../../load_env.php';
}
require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'common.php';



// gateway 进程，这里使用Text协议，可以用telnet测试
$gateway = new Gateway(env('GATEWAY'));
// gateway名称，status方便查看
$gateway->name = env('GATEWAY_NAME');
// gateway进程数
$gateway->count = intval(env('GATEWAY_COUNT'));
// 本机ip，分布式部署时使用内网ip
$gateway->lanIp = env('GATEWAY_LAN_IP');
// 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
// 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 
$gateway->startPort = intval(env('GATEWAY_START_PORT'));
// 服务注册地址
$gateway->registerAddress = env('REGISTER_ADDRESS');

// 心跳间隔
$gateway->pingInterval = intval(env('GATEWAY_PING_INTERVAL'));
// 心跳数据
//$gateway->pingData = '{"type":"ping"}';
//$gateway->pingData = '2';

/*
 * 其中pingNotResponseLimit = 0代表服务端允许客户端不发送心跳，服务端不会因为客户端长时间没发送数据而断开连接。
 * 如果pingNotResponseLimit = 1，则代表客户端必须定时发送数据给服务端，否则pingNotResponseLimit*pingInterval=55秒内没有任何数据发来则关闭对应连接，并触发onClose。
 */
$gateway->pingNotResponseLimit = intval(env('GATEWAY_PING_NOT_RESPONSE_LIMIT'));

/* 
// 当客户端连接上来时，设置连接的onWebSocketConnect，即在websocket握手时的回调
$gateway->onConnect = function($connection)
{
    $connection->onWebSocketConnect = function($connection , $http_header)
    {
        // 可以在这里判断连接来源是否合法，不合法就关掉连接
        // $_SERVER['HTTP_ORIGIN']标识来自哪个站点的页面发起的websocket链接
        if($_SERVER['HTTP_ORIGIN'] != 'http://kedou.workerman.net')
        {
            $connection->close();
        }
        // onWebSocketConnect 里面$_GET $_SERVER是可用的
        // var_dump($_GET, $_SERVER);
    };
}; 
*/

/**
 * 初始化数据
 * lwj 2023.9.18 新增
 * @return void
 */
$gateway->onWorkerStart = function(): void
{
    if(env('USE_GLOBALDATA')){
        global $global_data;
        $global_data = new GlobalData(env('GLOBALDATA_ADDRESS'));
    }
};

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

