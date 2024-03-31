GatewayWorker socket.io 版本
=================

用GatewayWorker实现socket.io，基于WebSocket，不支持http长轮询。

```
注意：需要php8.0以上版本，否则需要把php8以上的函数和代码改掉
```

使用下面技术实现
=======
[GatewayWorker](https://github.com/walkor/GatewayWorker)
[phpsocket.io](https://github.com/walkor/phpsocket.io)

启动
=======
和GatewayWorker使用方法基本一样

Linux： php start.php start

windows：双击start_for_win.bat

## 使用一般只需关注IoEvents.php文件

```
注意：这个和GatewayWorker不一样，因为Events已用于socket.io消息处理
```


## 配置在.env文件

```
#是否允许日记输出
APP_DEBUG=true

#是否允许跨域
ALLOW_CORS=true

#消息模式，1：由Events.php处理，2：由IoEvents.php处理，3：共同处理
MSG_HANDLE_MODE=1

#需要登录
NEED_LOGIN=true
#登录调用方法
LOGIN_FUNCTION=login
#保存在session中的tag来验证是否登录，设置为true是已登录
LOGIN_TAG=auth

#主要配置
GATEWAY=SocketIO://0.0.0.0:11111
REGISTER=text://0.0.0.0:11112
REGISTER_ADDRESS=127.0.0.1:11112

#网关配置
GATEWAY_NAME=SocketIO
GATEWAY_COUNT=4
GATEWAY_PING_INTERVAL=30
GATEWAY_PING_INTERVAL_EIO4=20
GATEWAY_PING_NOT_RESPONSE_LIMIT=1
GATEWAY_START_PORT=11115
GATEWAY_LAN_IP=127.0.0.1

#业务进程配置
WORKER_NAME=业务进程
WORKER_COUNT=16

#使用共享变量，目前只有服务器端的ack用到，不使用服务器端的ack可关闭
USE_GLOBALDATA=true
#变量共享组件
GLOBALDATA_HOST=127.0.0.1
GLOBALDATA_PORT=11113
GLOBALDATA_ADDRESS=127.0.0.1:11113
```

## IoEvents.php文件参考

一个socket.io事件对应一个静态方法

```php
<?php
/** @noinspection PhpUnused */

use GatewayWorker\Lib\Gateway;
use Workerman\Timer;

/**
 * 无特殊情况，只用关注本文件即可
 * 不需要的方法都可以删除
 * 可以使用GatewayWorker的所有功能
 * 发送消息，需使用emit_msg编码消息发送，请参考示例
 */

class IoEvents
{
    public static function onWorkerStart($businessWorker): void
    {
        try {
            if($businessWorker->id===0){
                global $global_data;
                $global_data->group_list=create_group_list();
                Timer::add(10, function(){
                    Gateway::sendToAll(emit_msg(
                        'online_count',
                        Gateway::getAllClientIdCount()
                    ));
                });
            }
        } catch (Throwable $e) {
            write_log('IoEvents-onWorkerStart：异常==》'.$e->getMessage(),'错误');
        }
    }

    public static function onConnect(int|string $client_id): void
    {
//        write_log('IoEvents-连接==》'.$client_id);
        try {
            $_SESSION['auth']=false;
            // 连接到来后，定时30秒关闭这个链接，需要30秒内发认证并删除定时器阻止关闭连接的执行
            $_SESSION['auth_timer_id'] = Timer::add(30, function($client_id){
                $session=Gateway::getSession($client_id);
                if(empty($session)) return;
                if(empty($session['auth'])){
                    write_log('IoEvents连接：监测到未登录，断开==》'.$client_id);
                    Gateway::closeClient($client_id);
                }else{
                    write_log('IoEvents连接：登录成功==》'.$client_id);
                }
            }, array($client_id), false);
//            write_log('IoEvents连接：完成==》'.$client_id);
        } catch (Throwable $e) {
            write_log('IoEvents连接：异常==》'.$client_id.'，异常内容==》'.$e->getMessage(),'错误');
        }
    }

    public static function login(int|string $client_id,$msg): void
    {
        write_log('IoEvents-login==》'.$client_id.json_encode_cn($msg));

        Gateway::bindUid($client_id, $msg['id']);
        $_SESSION['auth']=true;
        $_SESSION['user_info']=$msg;
        Timer::del($_SESSION['auth_timer_id']);

        global $global_data;
        $group_list=$global_data->group_list;
        foreach ($group_list as $v){
            Gateway::joinGroup($client_id, $v['id']);
        }
        $user_list = array_merge([$msg], $group_list);
        Gateway::sendToCurrentClient(emit_msg(
            'login_success',$user_list
        ));
    }

    public static function send_msg(int|string $client_id,$msg): void
    {
        write_log('IoEvents-send_msg==》'.json_encode_cn($msg));
        if($msg['user_type']===1){
            Gateway::sendToUid(
                $msg['receive_id'],
                emit_msg('send_msg',$msg)
            );
        }else{
            Gateway::sendToGroup(
                $msg['receive_id'],
                emit_msg('send_msg',$msg),
                [$client_id]
            );
        }
    }

    public static function exit_group(int|string $client_id,$msg): void
    {
        Gateway::leaveGroup($client_id, $msg['id']);
    }

    public static function onClose(int|string $client_id): void
    {
        try {
//            write_log('IoEvents-离开==》'.$client_id);
            Timer::del($_SESSION['auth_timer_id']);
        } catch (Throwable $e) {
            write_log('IoEvents断开连接：异常==》'.$client_id.'，异常内容==》'.$e->getMessage(),'错误');
        }
    }

    public static function ferret(int|string $client_id,$ack,$msg2): void
    {
        Gateway::sendToCurrentClient(emit_msg_ack_res(
            $ack,[
                'client_id'=>$client_id,
                'ss'=>'wood'
            ]
        ));

        Gateway::sendToCurrentClient(emit_msg_ack(
            'hello',
            'abc',
            [
                'client_id'=>$client_id,
                'kk'=>'wood'
            ]
        ));
    }

    public static function abc(int|string $client_id,...$msg): void
    {
        write_log('IoEvents-abc==》'.json_encode_cn($msg));
    }

    public static function HandleAck(int|string $client_id,int|string $ack_sn,...$msg): void
    {
        write_log('IoEvents-HandleAck==》'.json_encode_cn($msg));
    }
}
```

## 客户端判断全部连接成功以connect_success事件为准

```
比如判断连接成功后登录
```

## 发送消息

需要调用emit_msg发送消息，emit_msg方法参考如下
第一个参数是事件名称，第二个参数是消息内容，消息内容可以是数组，也可以是字符串，数字等

```php
Gateway::sendToCurrentClient(emit_msg(
        'connect_success',[
        'client_id'=>$client_id,
        'sid'=>$_SESSION['sid']
        ]
));
```



更多使用参考GatewayWorker手册
=======
http://www.workerman.net/gatewaydoc/


