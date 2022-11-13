<?php
namespace App\Provider;

use App\Provider\Music\Format;
use App\Provider\Music\NasMedia;
use App\Provider\Music\Netease;
use App\Util\DataUtil;

class MusicProvider
{
    private $dataUtil;

    private $keyword;

    private $nasMedia;

    public function __construct()
    {
        $this->nasMedia = new NasMedia();
        //$this->nasMedia->loadPlayList();
    }

    public function onRecvData($data)
    {
        $this->dataUtil = new DataUtil($data);
    }

    public function buildData()
    {
        return $this->dataUtil->build();
    }

    public function isMusic()
    {
        $body = $this->dataUtil->getBody();
        print_r($body);
        echo "isMusic text:" . $body['text'] . PHP_EOL;
        if (empty($body) || !isset($body['code']) || !isset($body['text'])) {
            echo "isMusic false" . PHP_EOL;
            return false;
        }
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
            echo "isMusic 播放xxx的歌" . PHP_EOL;
            return true;
        }

        if (preg_match('#^播放(.*?)的(.*)#isu', $body['text'], $matched)) {
            $this->keyword = $matched[2] . ' ' . $matched[1];
            echo "isMusic 播放xxx的xxxx" . PHP_EOL;
            return true;
        }

        if (preg_match('#^播放(.*?)#isuU', $body['text'], $matched)) {
            $this->keyword = $matched[1];
            echo "isMusic 播放xxx" . PHP_EOL;
            return true;
        }

        echo "isMusic: false" . PHP_EOL;
        return false;
    }

    public function processNasCmd()
    {
        $body = $this->dataUtil->getBody();
        if (!isset($body['semantic'])) {
            echo "processNasCmd, has no semantic body" . PHP_EOL;
            print_r($body);
            //$this->dataUtil->generateCodeBody();
            return false;
        }

        $format = new Format();
        $format->setSemantic($body['semantic']);

        $res = $this->nasMedia->processCtrlCommand($body['text'], $format);
        if ($res) {
            //$this->dataUtil->setBody($format->getData());
        }
        return $res;
    }

    public function searchNasMedia()
    {
        $body = $this->dataUtil->getBody();
        if (!isset($body['semantic'])) {
            echo "processNasMedia, has no semantic body" . PHP_EOL;
            print_r($body);

            if (isset($body['text']) && preg_match('#^播放(.*)#isu', $body['text'], $matched)) {
                //$text = $body['text']; // save the original text
                $this->dataUtil->generateSemanticBody($body['text']);
                $body = $this->dataUtil->getBody();
                //$body['text'] = $text; // replace with the orignal text
                print_r($body);
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

    public function search()
    {
        echo "search internet with key word:" . $this->keyword . PHP_EOL;
        if ($this->isMusic() && !empty($this->keyword)) {
            $body = $this->dataUtil->getBody();
            $format = new Format();
            $format->setSemantic($body['semantic']);
            $format->setText($this->keyword)->setAsrText($this->keyword);
            (new Netease())->search($this->keyword, $format);
            $this->dataUtil->setBody($format->getData());
        } else {
            echo "search from internet, not music command" . PHP_EOL;
        }
    }
}
