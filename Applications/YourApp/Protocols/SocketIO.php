<?php
/** @noinspection PhpUnused */

namespace Protocols;

use Protocols\Http\Request;
use Protocols\Http\Response;
use Throwable;
use Workerman\Connection\TcpConnection;


class SocketIO
{
    /**
     * 检查包的完整性
     * 如果能够得到包长，则返回包的在buffer中的长度，否则返回0继续等待数据
     * 如果协议有问题，则可以返回false，当前客户端连接会因此断开
     * @param string $buffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        $pos = strpos($buffer, "\r\n\r\n");
        if (! $pos) {
            if (strlen($buffer) >= $connection->maxPackageSize) {
                $connection->close("HTTP/1.1 400 bad request\r\n\r\nheader too long");
                return 0;
            }
            return 0;
        }
        $head_len = $pos + 4;
        $all_len = strlen($buffer);
        $raw_head = substr($buffer, 0, $head_len);
        $raw_body = substr($buffer, $head_len);
        $req = new Request($connection, $raw_head);
        $headers=$req->headers;
        $content_type = $headers['content-type'] ?? '';
        $res = new Response($connection);
        $connection->httpRequest = $req;
        $connection->httpResponse = $res;
        TcpConnection::$statistics['total_request']++;

        if ('OPTIONS' === $req->method) {
            echo 'OPTIONS'.PHP_EOL;
            $connection->consumeRecvBuffer($all_len);
            $headers = self::headers($req);
            $headers['Access-Control-Allow-Headers'] = 'Content-Type';
            $res->writeHead(200, '', $headers);
            $res->end();
            $connection->close();
            return 0;
        }

        if ('GET' === $req->method || 'POST' === $req->method) {
            $get_params=self::parse_get_params($req->url);
            $post_params=self::parse_post_params($raw_body,$content_type);
            if(!empty($get_params['EIO']) && !empty($get_params['transport'])) {

                if (isset($req->headers['upgrade']) && strtolower($req->headers['upgrade']) === 'websocket') {
                    WebSocket::dealHandshake($connection, $req, $res);
                    $connection->msg_data=json_encode_cn([
                        'type'=>'socket连接',
                        'method'=>$req->method,
                        'sid'=>$get_params['sid']??'',
                        'EIO'=>$get_params['EIO'],
                        'get_params'=>$get_params,
                        'post_params'=>$post_params,
                    ]);
                    return $all_len;
                }

                if (empty($get_params['sid'])) {
                    $connection->msg_data=json_encode_cn([
                        'type'=>'初始连接',
                        'method'=>$req->method,
                        'EIO'=>$get_params['EIO'],
                        'get_params'=>$get_params,
                        'post_params'=>$post_params,
                    ]);
                }else{
                    $connection->msg_data=json_encode_cn([
                        'type'=>'后续连接',
                        'method'=>$req->method,
                        'sid'=>$get_params['sid'],
                        'EIO'=>$get_params['EIO'],
                        'get_params'=>$get_params,
                        'post_params'=>$post_params,
                    ]);
                }
                return $all_len;
            }
        }

        $connection->close("HTTP/1.1 400 bad request\r\n\r\nrequest not support");
        return 0;
    }

    /**
     * 打包，当向客户端发送数据的时候会自动调用
     * @param string $buffer
     * @param TcpConnection $connection
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        if(empty($connection->httpResponse)) return $buffer;
        if(env('ALLOW_CORS')){
            $connection->httpResponse->setHeader('Access-Control-Allow-Origin', '*');
            $connection->httpResponse->setHeader('X-XSS-Protection', '0');
        }

        $connection->httpResponse->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $connection->httpResponse->setHeader('Content-Length', strlen($buffer));
        return $connection->httpResponse->getHeadBuffer() . $buffer;
    }

    /**
     * 解包，当接收到的数据字节数等于input返回的值（大于0的值）自动调用
     * 并传递给onMessage回调函数的$data参数
     * @param string $buffer
     * @param TcpConnection $connection
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        if(empty($connection->msg_data)) return $buffer;
        return $connection->msg_data;
    }

    public static function cleanup($connection): void
    {
        if (! empty($connection->onRequest)) {
            $connection->onRequest = null;
        }
        if (! empty($connection->onWebSocketConnect)) {
            $connection->onWebSocketConnect = null;
        }
        if (! empty($connection->httpRequest)) {
            $connection->httpRequest->destroy();
            $connection->httpRequest = null;
        }
        if (! empty($connection->httpResponse)) {
            $connection->httpResponse->destroy();
            $connection->httpResponse = null;
        }
    }

    public static function headers($req, $headers = [])
    {
        if (isset($req->headers['origin'])) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
            $headers['Access-Control-Allow-Origin'] = $req->headers['origin'];
        } else {
            $headers['Access-Control-Allow-Origin'] = '*';
        }
        return $headers;
    }

    public static function encode_res($type,$response): string
    {
        $body_len = strlen((string)$response)+strlen((string)$type);
        return $body_len.":".$type."$response";
    }

    public static function parse_get_params($url): array
    {
        try {
            $get_params=[];

            $urlParts = parse_url($url);
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $get_params);
            }
            return $get_params;
        } catch (Throwable) {
            return [];
        }
    }

    public static function parse_post_params($raw_body,$content_type)
    {
        try {
            if(empty($content_type)) return [];
            $post_params=[];
            if(str_contains($content_type, 'boundary')){
                $post_params=self::parse_form_data($raw_body);
            }else if(str_contains($content_type, 'json')){
                $post_params=json_decode($raw_body,true);
            }else if(str_contains($content_type, 'x-www-form-urlencoded')){
                parse_str($raw_body,$post_params);
            }else{
                $post_params=['msg'=>trim($raw_body)];
            }
            return $post_params;
        } catch (Throwable) {
            return [];
        }
    }

    public static function parse_form_data($data): array
    {
        // 解析form-data参数
        preg_match_all('/name="([^"]+)"\s*[\n\r]+([\s\S]*?)\s*-{2,}/', $data, $matches, PREG_SET_ORDER);
        // 将参数存储到关联数组中
        $params = [];
        foreach ($matches as $match) {
            $param_name = $match[1];
            $param_value = $match[2];
            $params[$param_name] = $param_value;
        }
        return $params;
    }


}