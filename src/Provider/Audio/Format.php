<?php
namespace App\Provider\Audio;

class Format
{
    private $data = [];

    public function __construct() {
        $this->data = json_decode('{
	  "source": "nlu",
	  "general": {
	  	"quitDialog": "true",
	  	"actionAble": "true",
	  	"type": "T",
	  	"text": "哎呦，今天我有点头疼，亲爱的，你没事吧？...."
	  },
	  "responseId": "7ae07b2dd9654e92bcf9d3e6175c893c",
	  "history": "cn.yunzhisheng.chat",
	  "text": "",
	  "originIntent": {
	  	"nluSlotInfos": []
	  },
	  "service": "cn.yunzhisheng.chat",
	  "asr_recongize": "",
	  "code": "ANSWER",
	  "rc": 0
	}', true);
    }

    public function setOriginIntent($originIntent) {
        $this->data['originIntent'] = $originIntent;
        return $this;
    }

    public function setAnswer($answer) {
        $this->data['general']['text'] = $answer;
        return $this;
    }

    public function setAsrResult($asrResult) {
        $this->data['asr_recongize'] = $asrResult;
        $this->data['text'] = $asrResult;
        return $this;
    }

    public function getData() {
        return $this->data;
    }
}
