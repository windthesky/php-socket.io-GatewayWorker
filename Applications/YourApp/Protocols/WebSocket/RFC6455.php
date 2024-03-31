<?php
/** @noinspection PhpDuplicateSwitchCaseBodyInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedFieldInspection */
/** @noinspection PhpUndefinedClassInspection */

/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Protocols\WebSocket;

use Exception;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;
use Workerman\Connection\TcpConnection;

/**
 * WebSocket 协议服务端解包和打包
 */
class RFC6455 implements ProtocolInterface
{
    /**
     * websocket头部最小长度
     *
     * @var int
     */
    const MIN_HEAD_LEN = 6;

    /**
     * websocket blob类型
     *
     * @var char
     */
    const BINARY_TYPE_BLOB = "\x81";

    /**
     * websocket arraybuffer类型
     *
     * @var char
     */
    const BINARY_TYPE_ARRAYBUFFER = "\x82";

    /**
     * 检查包的完整性
     *
     * @param string $recv_buffer
     */
    public static function input($recv_buffer, ConnectionInterface $connection)
    {
//        echo '进入websocket协议解析'.$recv_buffer.PHP_EOL;
        // 数据长度
        $recv_len = strlen($recv_buffer);
        // 长度不够
        if ($recv_len < self::MIN_HEAD_LEN) {
            return 0;
        }

        // $connection->websocketCurrentFrameLength有值说明当前fin为0，则缓冲websocket帧数据
        if ($connection->websocketCurrentFrameLength) {
            // 如果当前帧数据未收全，则继续收
            if ($connection->websocketCurrentFrameLength > $recv_len) {
                // 返回0，因为不清楚完整的数据包长度，需要等待fin=1的帧
                return 0;
            }
        } else {
            $data_len = ord($recv_buffer[1]) & 127;
            $firstbyte = ord($recv_buffer[0]);
            $is_fin_frame = $firstbyte >> 7;
            $opcode = $firstbyte & 0xf;
            switch ($opcode) {
                // 附加数据帧 @todo 实现附加数据帧
                case 0x0:
                    break;
                // 文本数据帧
                case 0x1:
                    break;
                // 二进制数据帧
                case 0x2:
                    break;
                // 关闭的包
                case 0x8:
                    // 如果有设置onWebSocketClose回调，尝试执行
                    if (isset($connection->onWebSocketClose)) {
                        call_user_func($connection->onWebSocketClose, $connection);
                    } // 默认行为是关闭连接
                    else {
                        $connection->close();
                    }
                    return 0;
                // ping的包
                case 0x9:
                    // 如果有设置onWebSocketPing回调，尝试执行
                    if (isset($connection->onWebSocketPing)) {
                        call_user_func($connection->onWebSocketPing, $connection);
                    } // 默认发送pong
                    else {
                        $connection->send(pack('H*', '8a00'), true);
                    }
                    // 从接受缓冲区中消费掉该数据包
                    if (! $data_len) {
                        $connection->consumeRecvBuffer(self::MIN_HEAD_LEN);
                        return 0;
                    }
                    break;
                // pong的包
                case 0xa:
                    // 如果有设置onWebSocketPong回调，尝试执行
                    if (isset($connection->onWebSocketPong)) {
                        call_user_func($connection->onWebSocketPong, $connection);
                    }
                    // 从接受缓冲区中消费掉该数据包
                    if (! $data_len) {
                        $connection->consumeRecvBuffer(self::MIN_HEAD_LEN);
                        return 0;
                    }
                    break;
                // 错误的opcode
                default:
                    echo "error opcode $opcode and close websocket connection\n";
                    $connection->close();
                    return 0;
            }

            // websocket二进制数据
            $head_len = self::MIN_HEAD_LEN;
            if ($data_len === 126) {
                $head_len = 8;
                if ($head_len > $recv_len) {
                    return 0;
                }
                $pack = unpack('ntotal_len', substr($recv_buffer, 2, 2));
                $data_len = $pack['total_len'];
            } elseif ($data_len === 127) {
                $head_len = 14;
                if ($head_len > $recv_len) {
                    return 0;
                }
                $arr = unpack('N2', substr($recv_buffer, 2, 8));
                $data_len = $arr[1] * 4294967296 + $arr[2];
            }
            $current_frame_length = $head_len + $data_len;
            if ($is_fin_frame) {
                return $current_frame_length;
            } else {
                $connection->websocketCurrentFrameLength = $current_frame_length;
            }
        }

        // 收到的数据刚好是一个frame
        if ($connection->websocketCurrentFrameLength == $recv_len) {
            self::decode($recv_buffer, $connection);
            $connection->consumeRecvBuffer($connection->websocketCurrentFrameLength);
            $connection->websocketCurrentFrameLength = 0;
            return 0;
        } // 收到的数据大于一个frame
        elseif ($connection->websocketCurrentFrameLength < $recv_len) {
            self::decode(substr($recv_buffer, 0, $connection->websocketCurrentFrameLength), $connection);
            $connection->consumeRecvBuffer($connection->websocketCurrentFrameLength);
            $current_frame_length = $connection->websocketCurrentFrameLength;
            $connection->websocketCurrentFrameLength = 0;
            // 继续读取下一个frame
            return self::input(substr($recv_buffer, $current_frame_length), $connection);
        } // 收到的数据不足一个frame
        else {
            return 0;
        }
    }

    /**
     * 打包，当向客户端发送数据的时候会自动调用
     *
     * @param string $data
     * @param ConnectionInterface $connection
     * @return string
     */
    public static function encode($data, ConnectionInterface $connection):string
    {
//        echo '进入websocket打包'.$data.PHP_EOL;
        $len = strlen($data);
        if (empty($connection->websocketHandshake)) {
            // 默认是utf8文本格式
            $connection->websocketType = self::BINARY_TYPE_BLOB;
        }

        $first_byte = $connection->websocketType;

        if ($len <= 125) {
            $encode_buffer = $first_byte . chr($len) . $data;
        } elseif ($len <= 65535) {
            $encode_buffer = $first_byte . chr(126) . pack("n", $len) . $data;
        } else {
            $encode_buffer = $first_byte . chr(127) . pack("xxxxN", $len) . $data;
        }

        // 还没握手不能发数据，先将数据缓冲起来，等握手完毕后发送
        if (empty($connection->websocketHandshake)) {
            if (empty($connection->websocketTmpData)) {
                // 临时数据缓冲
                $connection->websocketTmpData = '';
            }
            $connection->websocketTmpData .= $encode_buffer;
            // 返回空，阻止发送
            return '';
        }

        return $encode_buffer;
    }

    /**
     * 解包，当接收到的数据字节数等于input返回的值（大于0的值）自动调用
     *
     * @param string $recv_buffer
     * @param ConnectionInterface $connection
     * @return string
     */
    public static function decode($recv_buffer, ConnectionInterface $connection):string
    {
//        echo '进入websocket解包'.$recv_buffer.PHP_EOL;
        $len = $masks = $data = $decoded = null;
        $len = ord($recv_buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($recv_buffer, 4, 4);
            $data = substr($recv_buffer, 8);
        } elseif ($len === 127) {
            $masks = substr($recv_buffer, 10, 4);
            $data = substr($recv_buffer, 14);
        } else {
            $masks = substr($recv_buffer, 2, 4);
            $data = substr($recv_buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        if ($connection->websocketCurrentFrameLength) {
            $connection->websocketDataBuffer .= $decoded;

            return json_encode_cn([
                'type'=>'socket消息',
                'msg'=>$connection->websocketDataBuffer,
            ]);
//            return $connection->websocketDataBuffer;
        } else {
            $decoded = $connection->websocketDataBuffer . $decoded;
            $connection->websocketDataBuffer = '';
            return json_encode_cn([
                'type'=>'socket消息',
                'msg'=>$decoded,
            ]);
//            return $decoded;
        }
    }

    /**
     * 处理websocket握手
     *
     * @param TcpConnection $connection
     * @param $req
     * @param $res
     * @return int
     */
    public static function dealHandshake(TcpConnection $connection, $req, $res):int
    {
        $headers = [];
        if (isset($connection->onWebSocketConnect)) {
            try {
                call_user_func_array($connection->onWebSocketConnect, [$connection, $req, $res]);
            } catch (Exception $e) {
                echo $e;
            }
            if (! $res->writable) {
                return false;
            }
        }

        if (isset($req->headers['sec-websocket-key'])) {
            $sec_websocket_key = $req->headers['sec-websocket-key'];
        } else {
            $res->writeHead(400);
            $res->end('<b>400 Bad Request</b><br>Upgrade to websocket but Sec-WebSocket-Key not found.');
            return 0;
        }

        // 标记已经握手
        $connection->websocketHandshake = true;
        // 缓冲fin为0的包，直到fin为1
        $connection->websocketDataBuffer = '';
        // 当前数据帧的长度，可能是fin为0的帧，也可能是fin为1的帧
        $connection->websocketCurrentFrameLength = 0;
        // 当前帧的数据缓冲
        $connection->websocketCurrentFrameBuffer = '';
        // blob or arraybuffer
        $connection->websocketType = self::BINARY_TYPE_BLOB;

        $sec_websocket_accept = base64_encode(sha1($sec_websocket_key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $headers['Content-Length'] = 0;
        $headers['Upgrade'] = 'websocket';
        $headers['Sec-WebSocket-Version'] = 13;
        $headers['Connection'] = 'Upgrade';
        $headers['Sec-WebSocket-Accept'] = $sec_websocket_accept;
        $res->writeHead(101, '', $headers);
        $res->end();

        // 握手后有数据要发送
        if (! empty($connection->websocketTmpData)) {
            $connection->send($connection->websocketTmpData, true);
            $connection->websocketTmpData = '';
        }

        return 0;
    }
}
