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