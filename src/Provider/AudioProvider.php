<?php
namespace App\Provider;

use App\Provider\Audio\Format;
use App\Provider\Music\NasMedia;
use App\Util\DataUtil;
use App\Util\Logs;

class AudioProvider {
    private $dataUtil;

    private $keyword;

    private $nasMedia;

    public function __construct() {
        $this->nasMedia = new NasMedia();
    }

    public function onRecvData($data) {
        $this->dataUtil = new DataUtil($data);
    }

    public function buildData() {
        return $this->dataUtil->build();
    }


    public function processChatgpt() {
        $body = $this->dataUtil->getBody();

        if (isset($body['text']) && preg_match('#^请问(.*?)#isuU', $body['text'], $matched)) {
            $this->keyword = $matched[1];

            $format = new Format();
            $res = $this->requestChatgpt($body['text'], $format);

            if ($res) {
                $this->dataUtil->setBody($format->getData());
            }
	    return true;
        }

        return false;
    }

    public function processNasCmd() {
        $body = $this->dataUtil->getBody();

        $format = new Format();

        if(isset($body['text'])) {
          $res = $this->nasMedia->processCtrlCommand($body['text'], $format);
          if ($res) {
            $this->dataUtil->setBody($format->getData());
            return true;
          }
        }
        return false;
    }

    public function requestChatgpt($asr_result, $format) {
      $curl = curl_init();

      $postFields = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => [
          ["role" => "user", "content" => $asr_result]
        ]
      );

      curl_setopt_array($curl, array(
       	CURLOPT_URL => 'https://api.chatanywhere.tech/v1/chat/completions',
       	CURLOPT_RETURNTRANSFER => true,
       	CURLOPT_ENCODING => '',
       	CURLOPT_MAXREDIRS => 10,
       	CURLOPT_TIMEOUT => 0,
       	CURLOPT_FOLLOWLOCATION => true,
       	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
       	CURLOPT_CUSTOMREQUEST => 'POST',
       	CURLOPT_POSTFIELDS => json_encode($postFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
       	CURLOPT_HTTPHEADER => array(
       	   'Authorization: Bearer ' . file_get_contents('chatgpt.key'),
       	   'Content-Type: application/json'
       	),
     ));

     $response = curl_exec($curl);
     curl_close($curl);

     Logs::log("[AudioProvider] requestChatgpt" . $response);

     if ($response) {
       $decodedResponse = json_decode($response, true);
       $answer = "来自chat gpt的回答：" . $decodedResponse['choices'][0]['message']['content'];
       Logs::log("[AudioProvider] requestChatgpt reply:" . $answer);
       $format->setAnswer($answer)->setAsrResult($asr_result);
       //$format->setTtsUrl("http://192.168.1.3:8000/output.mp3");

       $res = $this->requestAzureTts($answer);
       if ($res == true) {
          Logs::log("[AudioProvider] requestChatgpt tts request succeed");
          $format->setTtsUrl("http://192.168.1.3:8000/output.mp3");
       }
       return true;
     } else {
         Logs::log("[AudioProvider] requestChatgpt, request failed.");
     }

     return false;
   }

   function requestAzureTts($text) {
    Logs::log('requestAzureTts enter');
    // 微软Azure语音合成REST API的终结点
    $endpoint = 'https://eastasia.tts.speech.microsoft.com/cognitiveservices/v1';

    // 你的Azure语音服务订阅密钥
    $subscriptionKey = file_get_contents('azure.key')

    // 要合成的文本
    // 设置请求头
    $headers = [
      'Content-Type: application/ssml+xml',
      'X-Microsoft-OutputFormat: audio-24khz-160kbitrate-mono-mp3',
      'Authorization: Bearer '. $this->getAzureAccessToken($subscriptionKey),
      'User-Agent: MySpeechApp'
    ];

    // 创建XML格式的请求体
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
     . '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="zh-CN">'
     . '<voice name="zh-CN-XiaoxiaoNeural">' . $text . '</voice>'
     . '</speak>';

    // 发送HTTP请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    Logs::log('requestAzureTts starts to request');
    $response = curl_exec($ch);
    
    $httpCode = curl_getinfo($ch , CURLINFO_HTTP_CODE);
    //print_r($httpCode);
    curl_close($ch);
    Logs::log('requestAzureTts after request');

    // 检查错误
    if ($httpCode != 200) {
        Logs::log('requestAzureTts Error:' . $httpCode);
        return false;
    } else {
        // 保存音频文件
        file_put_contents("output.mp3", $response);
        Logs::log("requestAzureTts Audio file saved as output.mp3");
        return true;
    }
  }

  // 获取访问令牌的函数
  function getAzureAccessToken($subscriptionKey) {
      $url = 'https://eastasia.api.cognitive.microsoft.com/sts/v1.0/issueToken';
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/x-www-form-urlencoded',
          'Content-Length: 0',
          'Ocp-Apim-Subscription-Key: '. $subscriptionKey
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $accessToken = curl_exec($ch);
      curl_close($ch);
      return $accessToken;
  }

   public function chatgpt() {
        $apiKey = "xxxx";
        $endpoint  = 'https://api.openai.com/v1/chat/completions';

	$question = "hello";
	$limit = 200;

    $data = array(
        "messages" => array(
            array("role" => "user", "content" => $question)
        ),
	"max_tokens" => $limit,
        "model" => "gpt-3.5-turbo"
    );

    $headers = array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    );

    $ch = curl_init($endpoint);

    //curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_HEADER, false);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response) {
            $decodedResponse = json_decode($response, true);
            echo "Generated reply: " . $decodedResponse['choices'][0]['message']['content'];
            print_r($decodedResponse);
        } else {
            echo "请求失败。";
        }
    }

    public function testCommand($cmd) {
        Logs::log("testCommand:" . $cmd);
        $format = new Format();
        $res = $this->nasMedia->processPlayCommand($cmd, $format);
        Logs::log("testCommand res:" . $res);
    }
}
