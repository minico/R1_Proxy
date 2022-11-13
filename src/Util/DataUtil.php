<?php

namespace App\Util;

class DataUtil
{
    private $httpInfo = [];
    private $bodyLength = 0;

    public function __construct($content)
    {
        $this->parse($content);
    }

    private function parse($content)
    {
        list($header, $body) = preg_split('#\r\n\r\n#isU', $content);
        $this->httpInfo['header'] = $header . "\r\n\r\n";
        $this->httpInfo['body'] = json_decode($body, true);
        $this->httpInfo['origin'] = $content;
    }

    public function getBody()
    {
        return $this->httpInfo['body'];
    }

    public function setBody($body)
    {
        $this->httpInfo['body'] = $body;
    }

    public function generateCodeBody()
    {
        echo "generateCodeBody\n";
        $this->httpInfo['body'] = json_decode('{"code":"ANSWER","originIntent":{"nluSlotInfos":[]},"history":"cn.yunzhisheng.chat","source":"krc","uniCarRet":{"result":[],"returnCode":609,"message":"http post reuqest error"},"asr_recongize":"12345。","rc":0,"general":{"resourceId":"bdzd_123_3693158","style":"CQA_baidu_zhidao","text":"上山打老虎"},"returnCode":0,"audioUrl":"http://asrv3.hivoice.cn/trafficRouter/r/YZvVpq","retTag":"nlu","service":"cn.yunzhisheng.chat","nluProcessTime":"320","text":"12345","responseId":"dc950b037aa24edbb35d6a89dc74c8ea"}', true);
    }

    public function generateSemanticBody($key_word)
    {
        echo "generateSemanticBody\n";
        $this->httpInfo['body'] = json_decode('{"semantic":{"intent":{"artist":"游鸿明","keyword":"游鸿明"}},"code":"SEARCH_CATEGORY","data":{"result":{"count":1,"musicinfo":[{"id":4384379,"errorCode":0,"duration":3511309,"lyric":"","album":"","title":"游鸿明3","artist":"游鸿明","hdImgUrl":"","isCollected":false,"url":"http://nas.ku8.fun:8888/music/./MP3/游鸿明 - 下沙.mp3"}],"totalTime":10,"pagesize":1,"errorCode":0,"page":"1","source":1,"dataSourceName":"我的音乐"}},"originIntent":{"nluSlotInfos":[]},"history":"cn.yunzhisheng.music","source":"nlu","uniCarRet":{"result":[],"returnCode":609,"message":"aios-home.hivoice.cn"},"asr_recongize":"游鸿明","rc":0,"general":{"actionAble":"true","quitDialog":"true","text":"游鸿明","type":"T"},"returnCode":0,"audioUrl":"http://asrv3.hivoice.cn/trafficRouter/r/wMgclE","retTag":"nlu","service":"cn.yunzhisheng.music","nluProcessTime":"106","text":"游鸿明","responseId":"e490d9576c5b438c8283a6e71cdba997"}', true);
        $this->httpInfo['body']['text'] = $key_word;
        $this->httpInfo['body']['asr_recongize'] = $key_word; 
    }

    public function build()
    {
        $bodyStr = $this->httpInfo['body'] ? json_encode($this->httpInfo['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $bodyLen = strlen($bodyStr);
        $this->httpInfo['header'] = preg_replace('#Content-Length: (\d+)\r\n\r\n#isU', "Content-Length: {$bodyLen}\r\n\r\n", $this->httpInfo['header']) ?? $this->httpInfo['header'];
        return $this->httpInfo['header'] . $bodyStr;
    }
}
