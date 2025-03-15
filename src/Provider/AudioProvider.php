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
       return true;
     } else {
         Logs::log("[AudioProvider] requestChatgpt, request failed.");
     }

     return false;
   }

   public function chatgpt() {
        $apiKey = "xxxx"
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
	$this->chatgpt2();
	return;

        Logs::log("testCommand:" . $cmd);
        $format = new Format();
        $res = $this->nasMedia->processPlayCommand($cmd, $format);
        Logs::log("testCommand res:" . $res);
    }
}
