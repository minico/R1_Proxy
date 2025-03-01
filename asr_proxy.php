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

Logs::log("Local server listened on port 80");

for($i = 0; $i < $argc; $i++) {
    echo "parameter " . $i . ":" . $argv[$i] . "\n";
}

$musicProvider = new MusicProvider();

if ($argc > 1) {
   echo "Please input test command\n>";
   $fin = fopen("php://stdin", "r");
   $cmd = fgets($fin);
   $cmd = str_replace("\n", "", $cmd);

   while(strcmp($cmd, "q") != 0) {
     if(!empty($cmd)) {
       $musicProvider->testCommand($cmd);
     }

     echo "Please input test command\n>";
     $fin = fopen ("php://stdin","r");
     $cmd = fgets($fin);
     $cmd = str_replace("\n", "", $cmd);
   }

   echo "exit test now.\n>";
   exit();
}

$local_server = new Swoole\Server('0.0.0.0', 80);

function connectAsrServer($local_server, $fd, $data) {
    //Logs::log("Local Server: Receive data from XiaoXun, forward it to asr server.\n");

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
        Logs::log("Failed to connect to asr server 47.102.50.144:80. Error: {$client->errCode}\n");
 	return;
    } 

    //Logs::log("connected to asr server 47.102.50.144:80 successfully\n");

    $client->send($data);

    $recv = $client->recv();

    //Logs::log("<<<<<< recv data from asr server:\n" .$recv);

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

    //Logs::log(">>>>>>>> forward data to XiaoXun:\n" . $data);

    $local_server->send($fd, $data);

    $client->close();
}

$local_server->on('WorkerStart', function ($local_server, $workerId) {
//    include_once "./vendor/autoload.php";
});

$local_server->on('Connect', function ($local_server, $fd) {
    Logs::log("Local Server: Connect from XiaoXun\n");
});

$local_server->on('Receive', function ($local_server, $fd, $reactor_id, $data) {
    // var_dump($data);
    connectAsrServer($local_server, $fd, $data);
});

$local_server->on('Close', function ($local_server, $fd) {
    Logs::log("Local Server: Close\n");
});

//启动服务器
$local_server->start();
