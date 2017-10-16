<?php
/**
 * Created by Jerry.
 * Date: 2017/10/14
 * Time: 16:58
 */

error_reporting(E_ALL);
set_time_limit(0);// 设置超时时间为无限,防止超时
date_default_timezone_set('Asia/shanghai');

class sw_websocket {
    const LOG_PATH = './tmp/';
    const LISTEN_SOCKET_NUM = 9;
    private $sockets = [];
    private $master;
    private $max = 0;
    private $server;
    public function __construct($host, $port)
    {
        try {
            $this->connect($host,$port);
            $this->onmessage();
            $this->onclose();
            $this->server->start();

        } catch (\Exception $e) {

            $this->error([
                'error_init_server',
                $e->getCode(),
                $e->getMessage()
            ]);
        }
    }
    public function dealMsg($recv_msg, $i) {
        $msg_type = $recv_msg['type'];
        $response = [];
        $touname = array();
        //添加@用户
        if (preg_match('/^@(\w+)\s(.+)$/', $recv_msg['content'], $tmp_msg_content)) {
            $msg_content = $tmp_msg_content[2];
            array_push($touname, $this->sockets[$i]['uname'], $tmp_msg_content[1]);
        } else {
            $msg_content = $recv_msg['content'];
        }
        switch ($msg_type) {
            case 'login':
                $this->sockets[$i]['uname'] = $msg_content;
                // 取得最新的名字记录
                $user_list = array_column($this->sockets, 'uname');
                $response['type'] = 'login';
                $response['content'] = $msg_content;
                $response['user_list'] = $user_list;
                break;
            case 'logout':
                $user_list = array_column($this->sockets, 'uname');
                $response['type'] = 'logout';
                $response['content'] = $msg_content;
                $response['user_list'] = $user_list;
                break;
            case 'user':
                $uname = $this->sockets[$i]['uname'];
                $response['type'] = 'user';
                $response['from'] = $uname;
                $response['content'] = $msg_content;
                break;
        }

        return array(json_encode($response), array_unique($touname));
    }
    public function connect($host,$port)
    {
        $this->server = new swoole_websocket_server($host, $port);
        $this->max = 0;
        $this->sockets = [];
        $this->server->on('open', function (swoole_websocket_server $server, $req)
        {
            //每一次客户端连接 最大连接数将增加
            
            $this->max++;
            $msg = [
                'type' => 'handshake',
                'content' => 'done',
            ];
            $this->server->push($req->fd, json_encode($msg));
        });
    }
    public function onmessage()
    {
        $this->server->on('message', function (swoole_websocket_server $server, $frame) {
            $fd = $frame->fd;
            list($data, $touname) = $this->dealMsg(json_decode($frame->data, 1), $fd);
            $message = "连接号{$fd}：内容：{$data}";
            //向所有人广播
            for ($i = 1; $i <= $this->max; $i++) {
                if ($touname) {
                    if (in_array($this->sockets[$i]['uname'], $touname)) {
                        $server->push($i, $data);
                    }
                } else {
                    $server->push($i, $data);
                }

                echo PHP_EOL . date('Y-m-d h:m:s') . ': ' . $fd . " : " . $data;
            }
        });
    }
    public function onclose()
    {
        $this->server->on('close', function (swoole_websocket_server $server, $fd) {
            //关闭连接 连接减少
            $this->max--;
            unset($this->sockets[$fd]);
            echo "client {$fd} closed\n";
        });
    }

    /**
     * 记录debug信息
     *
     * @param array $info
     */
    private function debug(array $info) {
        if(!is_dir(self::LOG_PATH)){
            mkdir(self::LOG_PATH,0777);
        }
        $time = date('Y-m-d H:i:s');
        array_unshift($info, $time);

        $info = array_map('json_encode', $info);
        file_put_contents(self::LOG_PATH . 'websocket_debug.log', implode(' | ', $info) . "\r\n", FILE_APPEND);
    }

    /**
     * 记录错误信息
     *
     * @param array $info
     */
    private function error(array $info) {
        if(!is_dir(self::LOG_PATH)){
            mkdir(self::LOG_PATH,0777);
        }
        $time = date('Y-m-d H:i:s');
        array_unshift($info, $time);

        $info = array_map('json_encode', $info);
        file_put_contents(self::LOG_PATH . 'websocket_error.log', implode(' | ', $info) . "\r\n", FILE_APPEND);
    }
}
$ws = new sw_websocket("0.0.0.0", "5555");
?>
