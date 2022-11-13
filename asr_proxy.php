<?php
/**
 * 语音劫持
 * host asrv3.hivoice.cn 解析到你的服务器
 * port 80
 * 这个域名有两个连接类型 http  tcp（websocket？）
 */

use App\Provider\MusicProvider;
use App\Util\Logs;
use Swoole\Coroutine\Client;

include_once "./vendor/autoload.php";

setlocale(LC_ALL, 'zh_CN.GBK');
Logs::setLogPath(dirname(__FILE__) . "/logs.log");

$server = new Swoole\Server('0.0.0.0', 80);
Logs::log("server listened on port 80");

$musicProvider = new MusicProvider();

function client($server, $fd, $data)
{
    $client = new Client(SWOOLE_SOCK_TCP);
    $client->set(array(
        'open_length_check' => true,
        'dispatch_mode' => 1,
        'package_length_func' => function ($data) {
            preg_match('#.*Content-Length: (\d+)\r\n\r\n#isU', $data, $matched);
            $headerLen = mb_strlen($matched[0]) ?? 0;
            $bodyLen = $matched[1] ?? 0;
            return intval($headerLen + $bodyLen);
        },
        'package_max_length' => 1024 * 1024 * 5,
    ));
    if (!$client->connect('47.102.50.144', 80, -1)) {
        Logs::log("connect failed. Error: {$client->errCode}\n");
    }
    $client->send($data);

    $recv = $client->recv();

    Logs::log("<<<<<< recv data from asr server:\n" .$recv);

    global $musicProvider;
    $musicProvider->onRecvData($recv);

    if (!$musicProvider->processNasCmd()) {
        if (!$musicProvider->searchNasMedia()) {
            if ($musicProvider->isMusic()) {
                $musicProvider->search();
            }
        }
    }

    /*
    if ($musicProvider->isMusic()) {
    $musicProvider->search();
    }*/

    $data = $musicProvider->buildData();

    Logs::log(">>>>>>>> send data to R1:\n" . $data);

    $server->send($fd, $data);

    $client->close();
}

$server->on('WorkerStart', function ($server, $workerId) {
//    include_once "./vendor/autoload.php";
});

$server->on('Connect', function ($server, $fd) {

});

$server->on('Receive', function ($server, $fd, $reactor_id, $data) {
    // var_dump($data);
    client($server, $fd, $data);
});

$server->on('Close', function ($server, $fd) {

});

//启动服务器
$server->start();
