<?php
use App\Util\Logs;
use Swoole\Http\Request;
use Swoole\Http\Response;

include_once "./vendor/autoload.php";

setlocale(LC_ALL, 'zh_CN.GBK');
Logs::setLogPath(dirname(__FILE__) . "/logs.log");

Logs::log("Http server listened on port 82");
$http_server = new Swoole\Http\Server('0.0.0.0', 82);

// 指定文件夹路径
$documentRoot = "../www";

// 监听请求事件
$http_server->on('request', function (Request $request, Response $response) use ($documentRoot) {
    // 获取请求的文件路径
    $filePath = $documentRoot. $request->server['request_uri'];

    // 判断文件是否存在
    if (is_file($filePath)) {
        // 设置响应头
        $response->header('Content-Type', mime_content_type($filePath));

        // 读取并输出文件内容
        $response->end(file_get_contents($filePath));
    } else {
        // 文件不存在，返回404错误
        $response->status(404);
        $response->end('File not found');
    }
});

$http_server->on('Connect', function ($local_server, $fd) {
    Logs::log("Http Server: Connect from http request\n");
});

$http_server->on('Receive', function ($local_server, $fd, $reactor_id, $data) {
    Logs::log("Http Server: Receive from http request:\n");
    var_dump($data);
});

$http_server->on('Close', function ($local_server, $fd) {
    Logs::log("Http Server: Close\n");
});

//启动服务器
$http_server->start();
