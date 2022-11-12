<?php
namespace App\Provider;

use App\Provider\Music\Netease;
use App\Util\DataUtil;
use App\Provider\Music\Migo;
use App\Provider\Music\Format;
use App\Provider\Music\NasMedia;


class MusicProvider
{
    private $dataUtil;

    private $keyword;
    
    private $nasMedia;

    public function __construct(DataUtil $dataUtil)
    {
        $this->dataUtil = $dataUtil;
        $this->nasMedia = new NasMedia();
        $this->nasMedia->loadPlayList();
    }

    public function isMusic() {
        $body = $this->dataUtil->getBody();
        if (empty($body)) {
            return false;
        }
        if ($body['code'] === 'SEARCH_ARTIST') {
            $this->keyword = $body['semantic']['intent']['keyword'];
            return true;
        }

        if ($body['code'] === 'SEARCH_CATEGORY') {
            $this->keyword = $body['semantic']['intent']['keyword'];
            return true;
        }

        if (preg_match('#^我想听(.*)的歌$#isuU', $body['text'], $matched)) {
            $this->keyword = $matched[1];
            return true;
        }

        if (preg_match('#^我想听(.*?)的(.*)#isu', $body['text'], $matched)) {
            $this->keyword = $matched[2] . ' ' . $matched[1];
            return true;
        }

        if (preg_match('#^播放(.*?)的(.*)#isu', $body['text'], $matched)) {
            $this->keyword = $matched[2] . ' ' . $matched[1];
            return true;
        }
        return false;
    }
    
    
    public function processNasCmd() {
        $body = $this->dataUtil->getBody();
	if (!isset($body['semantic'])) {
	  echo "processNasCmd, has no semantic body" . PHP_EOL;
	  $this->dataUtil->generateCodeBody();
	  return true;
	}

        $format = new Format();
        $format->setSemantic($body['semantic']);
        
        $res = $this->nasMedia->processCtrlCommand($body['text'], $format);
        if ($res) {
            $this->dataUtil->setBody($format->getData());
        }
        return $res;
    }

    
    public function searchNasMedia() {
        $body = $this->dataUtil->getBody();
	if (!isset($body['semantic'])) {
	  echo "processNasMedia, has no semantic body" . PHP_EOL;
	  return false;
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
        $body = $this->dataUtil->getBody();
        $format = new Format();
        $format->setSemantic($body['semantic']);
        $format->setText($this->keyword)->setAsrText($this->keyword);
        (new Netease())->search($this->keyword, $format);
        $this->dataUtil->setBody($format->getData());
    }
}
