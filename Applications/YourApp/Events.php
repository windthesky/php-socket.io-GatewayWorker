<?php
/** @noinspection PhpUnused */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use GatewayWorker\BusinessWorker;
use GatewayWorker\Lib\Gateway;
use Workerman\Timer;

require_once 'common.php';

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当businessWorker进程启动时触发。每个进程生命周期内都只会触发一次。
     */
    public static function onWorkerStart($businessWorker): void
    {
        try {
            if($businessWorker->id===0){
                Timer::add(env('GATEWAY_PING_INTERVAL_EIO4'), function(){
                    Gateway::sendToGroup('eio_4', '2');
                });
            }
            if(self::IOExists('onWorkerStart')){
                self::callIoEvents('onWorkerStart',$businessWorker);
            }
        } catch (Throwable $e) {
            write_log('onWorkerStart：异常==》'.$e->getMessage(),'错误');
        }
    }

    /**
     * 连接处理函数
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * @param int|string $client_id 连接id
     */
    public static function onConnect(int|string $client_id): void
    {
        try {
            if(self::IOExists('onConnect')){
                self::callIoEvents('onConnect',$client_id);
            }
        } catch (Throwable $e) {
            write_log('连接：异常==》'.$client_id.'，异常内容==》'.$e->getMessage(),'错误');
        }
    }

    /**
     * 当客户端连接上gateway完成websocket握手时触发的回调函数。
     * 注意：此回调只有gateway为websocket协议并且gateway没有设置onWebSocketConnect时才有效。
     * 这里SocketIO无效，忽略即可，SocketIO连接成功关注onSocketIOConnect
     */
    public static function onWebSocketConnect(int|string $client_id, mixed $message): void
    {
        try {
            if(self::IOExists('onWebSocketConnect')){
                self::callIoEvents('onWebSocketConnect',$client_id,$message);
            }
        } catch (Throwable $e) {
            write_log('onWebSocketConnect：异常==》'.$e->getMessage(),'错误');
        }
    }

    /**
     * 接收消息处理函数
     * 当客户端发来消息时触发
     * @param int|string $client_id 连接id
     * @param mixed $message 具体消息
     * @noinspection PhpIdempotentOperationInspection
     */
    public static function onMessage(int|string $client_id, mixed $message): void
    {
        try {
//            write_log('收到消息==》'.$message);
            if(env('ALLOW_CORS')!=='1' && self::IOExists('onMessage')){
                self::callIoEvents('onMessage',$client_id,$message);
                if(env('ALLOW_CORS')==='2') return;
            }

            $msg=json_decode($message,true);
            if($msg && !empty($msg['type'])){
                switch ($msg['type']){
                    case '初始连接':
                        $sid = 'sid_'.session_create_id();
                        Gateway::sendToCurrentClient(
                            self::sendHttpHandleEIO(
                                $msg['EIO'],
                                json_encode_cn([
                                'pingInterval'=>25000,
                                'pingTimeout'=>20000,
                                'sid'=>$sid,
                                'upgrades'=>['websocket'],
                                'maxPayload'=>1*1024*1024,
                            ]),0));
                        Gateway::closeClient($client_id);
                        break;
                    case 'socket连接':
                        if(!empty($msg['sid'])){
                            $EIO = intval($msg['EIO']);
                            $_SESSION['EIO'] = $EIO;
                            $_SESSION['sid'] = $msg['sid'];
                            $_SESSION['sid_state'] = 1;
                            Gateway::joinGroup($client_id, $msg['sid']);
                            if($EIO >= 4){
                                Gateway::joinGroup($client_id, 'eio_4');
                            }
                        }
                        break;
                    case '后续连接':
                        if($msg['method']==='POST'){
//                            self::HandleMsg42($msg['post_params']['msg'],$client_id,$msg['EIO']);
                            Gateway::sendToCurrentClient('OK');
                        }else{
                            Gateway::sendToCurrentClient(
                                self::sendHttpHandleEIO($msg['EIO'],
                                    json_encode_cn(['sid'=>$msg['sid']])
                                    ,40)
                            );
                        }
                        Gateway::closeClient($client_id);
                        break;
                    case 'socket消息':
                        switch ($msg['msg']){
                            case '2probe':
                                Gateway::sendToCurrentClient('3probe');
                                if(intval($_SESSION['EIO'])>=4){
                                    Gateway::sendToCurrentClient('2');
                                }
                                break;
                            case '2':
                                Gateway::sendToCurrentClient('3');
                                break;
                            case '5':
                                Gateway::sendToCurrentClient('6');
                                self::callSocketIOConnect($client_id);
                                Gateway::sendToCurrentClient(emit_msg(
                                    'connect_success',[
                                        'client_id'=>$client_id,
                                        'sid'=>$_SESSION['sid']
                                    ]
                                ));
                                break;
                            case '3':
                                break;
                            default:
                                $res=self::HandleMsg42($msg['msg'],$client_id);
                                if($res) return;
                                write_log('未定义socket消息类型：==》'.json_encode_cn($message),'警告');
                        }
                        break;
                    default:
                        write_log('未定义http消息类型：==》'.json_encode_cn($message),'警告');
                }
            }
        } catch (Throwable $e) {
            write_log('接收消息处理函数：异常==》'.$client_id.'，异常内容==》'.$e->getMessage(),'错误');
        }
    }

    /**
     * 用户断开连接触发函数
     * 当用户断开连接时触发（服务器断开基本不会触发）
     * @param int|string $client_id 连接id
     */
    public static function onClose(int|string $client_id): void
    {
        try {
            if(self::IOExists('onClose')){
                self::callIoEvents('onClose',$client_id);
            }
        } catch (Throwable $e) {
            write_log('断开连接：异常==》'.$client_id.'，异常内容==》'.$e->getMessage(),'错误');
        }
    }

    /**
     * 当businessWorker进程退出时触发。每个进程生命周期内都只会触发一次。
     * @param BusinessWorker $businessWorker
     */
    public static function onWorkerStop(BusinessWorker $businessWorker): void
    {
        try {
            if(self::IOExists('onWorkerStop')){
                self::callIoEvents('onWorkerStop',$businessWorker);
            }
        } catch (Throwable $e) {
            write_log('断开连接：异常==》'.$e->getMessage(),'错误');
        }
    }

    protected static function callIoEvents(string $f_name, ...$args): bool
    {
        try {
            call_user_func(array('IoEvents', $f_name), ...$args);
            return true;
        } catch (Throwable $e) {
            write_log(
                '调用IoEvents：异常==》' .$f_name
                .'，参数==》' .json_encode_cn($args)
                .'，异常内容==》' .$e->getMessage()
                ,'错误'
            );
        }
        return false;
    }


    protected static function sendHttpHandleEIO(string $EIO,string $msg,string|int $type): string
    {
        if(intval($EIO)<=3){
            $body_len = strlen((string)$type)+strlen($msg);
            return $body_len.":".$type.$msg;
        }
        return $type .$msg;
    }

    protected static function HandleMsg42(string $msg,string|int $client_id,string|int $EIO=4): bool
    {
        if(intval($EIO)<=3){
            list(, $msg) = explode(':', $msg, 2);
        }

        $f=mb_substr($msg, 0, 2, 'UTF-8');
        if($f==='42'){
            $index=mb_substr($msg, 2, 1, 'UTF-8');
            if($index !== '['){
                $pattern = '/^42(\d+)(\[.*])$/';
                preg_match($pattern, $msg, $matches);
                if(count($matches) !== 3) return false;

                $ack = $matches[1]; // 提取可变部分序号
                $v = $matches[2]; // 提取数组字符串部分
                $v_arr = json_decode($v, true);
                if($v_arr && is_array($v_arr) && count($v_arr)>=2){
                    $f = array_shift($v_arr);
                    if(!self::IsLogin($f)) return true;
                    self::callIoEvents($f,$client_id,$ack,...$v_arr);
                    return true;
                }
                return false;
            }

            $v=mb_substr($msg, 2, null, 'UTF-8');
            $v_arr = json_decode($v, true);
            if($v_arr && is_array($v_arr) && count($v_arr)>=2){
                $f = array_shift($v_arr);
                if(!self::IsLogin($f)) return true;
                self::callIoEvents($f,$client_id,...$v_arr);
                return true;
            }
        }elseif($f==='43'){
            if(!self::IsLogin()) return true;
            $msg_position = strpos($msg, '[');
            $ack_sn=mb_substr($msg, 2, $msg_position-2, 'UTF-8');
            $v=mb_substr($msg, $msg_position, null, 'UTF-8');
            $v_arr = json_decode($v, true);
            self::callHandleAck($client_id,$ack_sn, ...$v_arr);
            return true;
        }elseif($f==='41'){
            // 客户端主动断开连接
            self::closeClient($client_id);
            return true;
        }
        return false;
    }

    protected static function IOExists(string $f): bool
    {
        return class_exists('IoEvents') && method_exists('IoEvents', $f);
    }

    protected static function callSocketIOConnect(string|int $client_id): void
    {
        try {
            if(self::IOExists('onSocketIOConnect')){
                self::callIoEvents('onSocketIOConnect',$client_id);
            }
        } catch (Throwable $e) {
            write_log('onSocketIOConnect：异常==》'.$e->getMessage(),'错误');
        }
    }

    protected static function callHandleAck(string|int $client_id,string|int $ack_sn, ...$v_arr): void
    {
        try {
            if(self::IOExists('HandleAck')){
                self::callIoEvents('HandleAck',$client_id,$ack_sn, ...$v_arr);
            }
        } catch (Throwable $e) {
            write_log('HandleAck：异常==》'.$e->getMessage(),'错误');
        }
    }

    protected static function closeClient(string|int $client_id): void
    {
        try {
            Gateway::closeClient($client_id);
        } catch (Throwable $e) {
            write_log('closeClient：异常==》'.$e->getMessage(),'错误');
        }
    }

    protected static function IsLogin(string $f=''): bool
    {
        try {
            if(!env('NEED_LOGIN')) return true;
            if($f===env('LOGIN_FUNCTION')) return true;
            if(empty($_SESSION[env('LOGIN_TAG')])) return false;
            return true;
        } catch (Throwable $e) {
            write_log('IsLogin：异常==》'.$e->getMessage(),'错误');
            return false;
        }
    }

}
