<?php
namespace App\Provider;

use App\Provider\Music\Format;
use App\Provider\Music\NasMedia;
use App\Provider\Music\Netease;
use App\Util\DataUtil;
use App\Util\Logs;

class MusicProvider {
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

    public function isMusic() {
        $body = $this->dataUtil->getBody();
        //print_r("isMusic, body:" . $body);
        if (empty($body) || !isset($body['code']) || !isset($body['text'])) {
            //Logs::log("isMusic false");
            return false;
        }

        Logs::log("isMusic text:" . $body['text']);
        /*
        if ($body['code'] === 'SEARCH_ARTIST') {
            $this->keyword = $body['semantic']['intent']['keyword'];
            return true;
        }

        if ($body['code'] === 'SEARCH_CATEGORY') {
            $this->keyword = $body['semantic']['intent']['keyword'];
            return true;
        }*/

        if (preg_match('#^播放(.*)的歌$#isuU', $body['text'], $matched)) {
            $this->keyword = $matched[1];
            Logs::log("isMusic 播放xxx的歌");
            return true;
        }

        if (preg_match('#^播放(.*?)的(.*)#isu', $body['text'], $matched)) {
            $this->keyword = $matched[2] . ' ' . $matched[1];
            Logs::log("isMusic 播放xxx的xxxx");
            return true;
        }

        if (preg_match('#^播放(.*?)#isuU', $body['text'], $matched)) {
            $this->keyword = $matched[1];
            Logs::log("isMusic 播放xxx");
            return true;
        }

        Logs::log("isMusic: false");
        return false;
    }

    public function searchNasMedia() {
        $body = $this->dataUtil->getBody();
        if (!isset($body['semantic'])) {
            //Logs::log("processNasMedia, has no semantic body");
            //print_r($body);
            if (isset($body['text']) && preg_match('#^播放(.*)#isu', $body['text'], $matched)) {
                $this->dataUtil->generateSemanticBody($body['text']);
                $body = $this->dataUtil->getBody();
                //print_r($body);
            } else {
                return false;
            }
        }

        $format = new Format();
        $format->setSemantic($body['semantic']);

        $res = $this->nasMedia->processPlayCommand($body['text'], $format);
        if ($res) {
            $this->dataUtil->setBody($format->getData());
        }
        return $res;
    }

    public function search() {
        Logs::log("search internet with key word:" . $this->keyword);
        if ($this->isMusic() && !empty($this->keyword)) {
            $body = $this->dataUtil->getBody();
            $format = new Format();
            $format->setSemantic($body['semantic']);
            $format->setText($this->keyword)->setAsrText($this->keyword);
            (new Netease())->search($this->keyword, $format);
            $this->dataUtil->setBody($format->getData());
        } else {
            Logs::log("search from internet, not music command or keyword is empty.");
        }
    }

    public function testCommand($cmd) {
        Logs::log("testCommand:" . $cmd);
        $format = new Format();
        $res = $this->nasMedia->processPlayCommand($cmd, $format);
        Logs::log("testCommand res:" . $res);
    }
}
