<?php
namespace App\Provider\Music;
use App\Util\Logs;

class NasMedia {
    private $SPEECH_TEXT_WAKEUP_RESPONSE = "我在";
    private $SPEECH_TEXT_FAILED_RECOGNIZE = "对不起，我没听清。";
    private $SPEECH_TEXT_PLAY = "播放";
    private $SPEECH_TEXT_PLAY_RANDOM = "随机播放";
    private $SPEECH_TEXT_ADD_FAVORITE = "收藏";
    private $SPEECH_TEXT_REMOVE_FAVORITE = "取消收藏";
    private $SPEECH_TEXT_PLAY_FAVORITES = "播放收藏";
    private $SPEECH_TEXT_PLAY_PREVIOUS = "上一";
    private $SPEECH_TEXT_PLAY_NEXT = "下一";
    private $SPEECH_TEXT_REPEAT_WHAT_YOU_SAID = "你说";
    private $SPEECH_TEXT_UPDATE_LIST = "更新播放列表";
    private $SPEECH_TEXT_LIST_COMMANDS = "你支持哪些命令";
    private $SPEECH_TEXT_NOT_FOUND = "抱歉，没有找到";

    private $NAS_URL = "http://192.168.1.4/";
    //private $NAS_URL = "http://10.0.0.4:8888/music/";
    private $PLAY_LIST_FILE = "play_list.txt";
    private $HISTORY_LIST_FILE = "history_list.txt";
    private $FAVORITE_LIST_FILE = "favorite_list.txt";

    private $media_list = array();
    private $favorite_list = array();
    private $history_list = array(); // 用于保存序列故事，以便下次顺序播放
    //private $last_name = ""; // 用于保存序列故事
    //private $last_seq = "";  // 用于保存序列故事
    private $current_audio = "";
    private $playing_favorite = false;
    private $playing_series = false;

    public function __construct() {
        $this->loadPlayList();
        $this->loadHistory();
        $this->loadFavorite();
    }

    private function getRootPath() {
        return dirname(__FILE__) . "/../../../";
    }

    private function cleanString($str) {
        Logs::log("cleanString:" . $str);
        //中文标点
        $char = "，。、！？：；﹑•＂…‘’“”〝〞∕¦‖—　〈〉﹞﹝「」‹›〖〗】【»«』『〕〔》《﹐¸﹕︰﹔！¡？¿﹖﹌﹏﹋＇´ˊˋ―﹫︳︴¯＿￣﹢﹦﹤‐­˜﹟﹩﹠﹪﹡﹨﹍﹉﹎﹊ˇ︵︶︷︸︹︿﹀︺︽︾ˉ﹁﹂﹃﹄︻︼()";
        $pattern = array(
            "/[[:punct:]]/i", //英文标点符号
            '/[' . $char . ']/u', //中文标点符号
            '/[ ]{2,}/',
        );
        $str = preg_replace($pattern, '', $str);
        return $str;
    }

    private function getBaseName($file_path) {
        $file_name = preg_replace('/^.+[\\\\\\/]/', '', $file_path);
        $base_name = str_replace(strrchr($file_name, "."), "", $file_name);
        return $base_name;
    }

    private function urlEncode($item) {
        return str_replace(" ", "%20", $item);
    }

    private function isChineseNum($num) {
        return !preg_match('/^[0-9]+$/', $num);
    }

    private function chineseNumToDigits($str) {
        //汉字装换数字的对照表
        $number_map = array('零' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9);
        $step_map = array('十' => 10, '百' => 100, '千' => 1000);
        $bigStep_map = array('万' => '10000', '亿' => 100000000);
        //操作数栈，值栈
        $opStack = array(1);
        $valStack = array();
        //以万,亿为分割单位对数字进行拆分计算
        //例如:三千五百万一千一百零五，对其进
        //行分别入op,val栈
        //                op 中为1,10000
        //                val中为1105,3500
        //最后将栈进行对应合并操作,得出操作数
        for ($i = mb_strlen($str) - 1; $i >= 0; $i--) {
            $_val = 0;
            $_step = 1;
            for ($j = $i; $j >= 0; $j--) {
                $_char = mb_substr($str, $j, 1);
                if (array_key_exists($_char, $number_map)) {
                    $_val += $_step * $number_map[$_char];
                }
                if (array_key_exists($_char, $step_map)) {
                    $_step = $step_map[$_char];
                }
                $i = $j;
                if (array_key_exists($_char, $bigStep_map)) {
                    array_push($opStack, $bigStep_map[$_char]);
                    break;
                }
            }
            array_push($valStack, $_val);
        }
        $number = 0;
        //合并操作数
        while (count($opStack) > 0) {
            $va = array_pop($valStack);
            $op = array_pop($opStack);
            $number += $va * $op;
        }
        //检查两栈是否都弹出完毕，否则表达式错误
        if (count($opStack) == 0 && count($valStack) == 0) {
            return $number;
        } else {
            return false;
        }
    }

    private function getNameFromItem($item) {
        Logs::log("getNameFromItem:" . $item);
        $paths = explode("/", $item);
	$num = count($paths); 
        if ($num > 1) {//ignore the 1st part of item
	   for($i=1; $i<$num; ++$i){ 
             $item = $item . $paths[$i];
           } 
        }
        Logs::log("getNameFromItem after split path:" . $item);

        if (preg_match('/[\x{4e00}-\x{9fa5}]+/u', $item, $matched)) {
            $name = $matched[0];
            Logs::log("getNameFromItem, matched name:" . $name);
            return $name;
        }
        Logs::log("getNameFromItem, not found matched");
        return false;
    }

    private function getSeqFromItem($item) {
        Logs::log("getSeqFromItem:" . $item);
        $paths = explode("/", $item);
        if (count($paths) > 1) {
            $item = $paths[count($paths)-2].$paths[count($paths)-1];
        }
        Logs::log("getSeqFromItem after split path:" . $item);

        if (preg_match_all('/(\d?\.?\d+)/', $item, $matched)) {
            if (count($matched[0]) > 1) {
                $seq = $matched[0][count($matched[0]) - 2]; // ignore the last digit '3' match in "mp3"
                if ($seq < 1000) { //忽略一些年份信息的数字，比如2009，2019等
                    Logs::log("getSeqFromItem:" . $item . " seq:" . $seq);
                    return $seq;
                }
            }
        }
        Logs::log("getSeqFromItem:" . $item . ", not found");
        return false;
    }

    public function loadPlayList() {
        $this->media_list = array();
        $res = file_get_contents($this->NAS_URL . $this->PLAY_LIST_FILE);
        if ($res) {
            $this->media_list = explode("\n", $res);
            file_put_contents($this->getRootPath() . $this->PLAY_LIST_FILE, $res);
            Logs::log("loadPlayList, list downloaded successfully, total count:" . count($this->media_list));
            return true;
        } else {
            $res = file_get_contents($this->PLAY_LIST_FILE);
            if ($res) {
                $this->media_list = explode("\n", $res);
                Logs::log("LoadPlayList, list was loaded from local cache, total count:" . count($this->media_list));
                return true;
            } else {
                Logs::log("LoadPlayList download failed.");
                return false;
            }
        }
    }

    public function loadHistory() {
        $res = file_get_contents($this->getRootPath() . $this->HISTORY_LIST_FILE);
        if ($res) {
            $this->history_list = explode("\n", $res);
            Logs::log("loadHistory successfully, total count:" . count($this->history_list));
            return true;
        } else {
            Logs::log("loadHistory failed.");
            return false;
        }
    }

    public function saveHistory() {
        file_put_contents($this->getRootPath() . $this->HISTORY_LIST_FILE, implode(PHP_EOL, $this->history_list));
    }

    public function addToHistory($item) {
        Logs::log("addToHistory:" . $item);
        $name = $this->getNameFromItem($item);
        foreach ($this->history_list as $key => $value) {
            if (strstr($value, $name)) {
                Logs::log("addToHistory, unset:" . $this->history_list[$key]);
                unset($this->history_list[$key]);
            }
        }
        array_push($this->history_list, $item);
        $this->saveHistory();
    }

    public function loadFavorite() {
        $res = file_get_contents($this->getRootPath() . $this->FAVORITE_LIST_FILE);
        if ($res) {
            $this->favorite_list = explode("\n", $res);
            Logs::log("loadFavorite successfully, total count:" . count($this->favorite_list));
            return true;
        } else {
            Logs::log("loadFavorte failed.");
            return false;
        }
    }

    public function saveFavorite() {
        file_put_contents($this->getRootPath() . $this->FAVORITE_LIST_FILE, implode(PHP_EOL, $this->favorite_list));
    }

    public function addFavorite($item) {
        if (array_search($item, $this->favorite_list) === false) {
            array_push($this->favorite_list, $item);
            $this->saveFavorite();
            Logs::log("addFavorite:" . $item . " successfully.");
        } else {
            Logs::log("addFavorite:" . $item . " has been existed already.");
        }
    }

    public function removeFavorite($item) {
        Logs::log("removeFavorite:" . $item);
        foreach (array_keys($this->favorite_list, $item) as $key) {
            unset($array[$key]);
        }
        $this->saveFavorite();
    }

    public function getNext() {
        Logs::log("getNext, current_audio:" . $this->current_audio . " playing_series:" . $this->playing_series . " palying_favorite:" . $this->playing_favorite);
        if ($this->playing_series) {
            $name = $this->getNameFromItem($this->current_audio);
            $seq = $this->getSeqFromItem($this->current_audio);
            if ($seq !== false) {
                $seq += 1;
            } else {
                $seq = "";
            }
            $res = $this->getItemOfSpecified($name, $seq);
            Logs::log("getNext, found series, name:" . $name, " and seq:" . $seq . ", current_audio:" . $this->current_audio . ", play url:" . $res);
            return $res;
        }

        $tmpList = $this->media_list;
        if ($this->playing_favorite) {
            $tmpList = $this->favorite_list;
        }
        Logs::log("getNext, list count:" . count($tmpList));
        $key = array_search($this->current_audio, $tmpList);
        if ($key !== false) {
            if ($key < 0 || $key + 1 == count($tmpList)) {
                $key = -1;
            }
            $this->current_audio = $tmpList[$key + 1];
            $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
            Logs::log("getNext, current_audio:" . $this->current_audio . ", play url:" . $res);
            return $res;
        }
        return false;
    }

    public function getPrevious() {
        Logs::log("getPrevious, current_audio:" . $this->current_audio . " playing_series:" . $this->playing_series . " palying_favorite:" . $this->playing_favorite);
        if ($this->playing_series) {
            $name = $this->getNameFromItem($this->current_audio);
            $seq = $this->getSeqFromItem($this->current_audio);
            if ($seq !== false) {
                $seq -= 1;
                if ($seq < 1) {
                    $seq = 1;
                }
            } else {
                $seq = "";
            }
            $res = $this->getItemOfSpecified($name, $seq);
            Logs::log("getPrevious, found series, name:" . $name, " and seq:" . $seq . ", current_audio:" . $this->current_audio . ", play url:" . $res);
            return $res;
        }

        $tmpList = $this->media_list;
        if ($this->playing_favorite) {
            $tmpList = $this->favorite_list;
        }
        $key = array_search($this->current_audio, $tmpList);
        if ($key !== false) {
            if ($key <= 0) {
                $key = count($tmpList);
            }
            $this->current_audio = $tmpList[$key - 1];
            $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
            Logs::log("getPrevious, current_audio:" . $this->current_audio . ", play url:" . $res);
            return $res;
        }
        return false;
    }

    public function getRandomAudio() {
        $this->current_audio = $this->media_list[rand(0, count($this->media_list))];
        $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
        Logs::log("getRandomAudio, current_audio:" . $this->current_audio . ", play url:" . $res);
        return $res;
    }

    public function getFirstFavorite() {
        if (count($this->favorite_list) > 0) {
            $this->playing_favorite = true;
            $this->current_audio = $this->favorite_list[0];
            $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
            Logs::log("getFirstFavorite, current_audio:" . $this->current_audio . ", play url:" . $res);
            return $res; 
        }
        return false;
    }

    private function getItemOfSinger($singer) {
        Logs::log("getItemOfSinger:" . $singer . "media list count:" . count($this->media_list));
        $singerList = array();
        foreach ($this->media_list as $item) {
            if (strstr($item, $singer)) {
                array_push($singerList, $item);
            }
        }
        Logs::log("getItemOfSinger count:" . count($singerList));

        if (count($singerList) > 0) {
            $this->current_audio = $singerList[rand(0, count($singerList) - 1)];
            $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
            Logs::log("getItemOfSinger found:" . $this->current_audio . " play url:" . $res);
            return $res;
        }

        Logs::log("getItemOfSinger, no item found");
        return false;
    }

    private function getItemOfSpecified($name, $seq) {
        Logs::log("getItemOfSpecified by name:" . $name . " and seq:" . $seq);
        foreach ($this->history_list as $item) {
            if (empty($seq) && !empty($name) && strstr($item, $name)) {
                $name = $this->getNameFromItem($item);
                $seq = $this->getSeqFromItem($item);
                Logs::log("getItemOfSpecified, found in history");
                if ($seq !== false) {
                    $seq += 1;
                } else {
                    $seq = "";
                }
            }
        }

        Logs::log("getItemOfSpecified, new name:" . $name . " new seq:" . $seq);

        foreach ($this->media_list as $item) {
            if (preg_match('#(.*)' . $name . '(.*)0*' . $seq . '(.*)mp3#isuU', $item, $matched)) {
                $this->current_audio = $item;
                Logs::log("getItemOfSpecified, found item:" . $item);
                if ($seq !== false) {
                    $this->playing_series = true;
                }
                $res = $this->NAS_URL . $this->urlEncode($this->current_audio);
                Logs::log("getItemOfSpecified found,current audio:" . $this->current_audio . " play url:" . $res);
                return $res;
            }
        }
        Logs::log("getItemOfSpecified, no item found");
        return false;
    }

    private function generatePlayList() {
        Logs::log("generatePlayList, current_audio:" . $this->current_audio);
        if (empty($this->current_audio)) {
            Logs::log("generatePlayList, return false");
            return false;
        }

        $play_list = array();
        $i = 0;
        if (($key = array_search($this->current_audio, ($this->media_list))) != NULL) {
            while ($i++ < 10) {
                $idx = $key + $i;
                if ($idx < count($this->media_list)) {
                    array_push($play_list, $this->media_list[$idx]);
                    Logs::log("generatePlayList, add item:" . $this->media_list[$idx]);
                }
            }
        }
        return $play_list;
    }

    public function getMatchedAudio($asr_result) {
        $name = "";
        $seq = "";
        $res = false;
        $specified_singer = false;

        $this->playing_favorite = false;
        $this->playing_series = false;
        $this->current_audio = "";

        Logs::log("getMatchedAudio prepare to find match text:" . $asr_result);

        if (preg_match('#(.+)第(.*)集$#isuU', $asr_result, $matched)) {
            $name = $matched[1];
            $seq = $matched[2];
            if ($this->isChineseNum($seq)) {
                $seq = $this->chineseNumToDigits($seq);
            }
            $playing_series = true;
            Logs::log("getMatchedAudio match series mode1, name:" . $name . ", seq:" . $seq);
        } else if (preg_match('#([\x{4e00}-\x{9fa5}]+)([0-9]+)#isu', $asr_result, $matched)) {
            $name = $matched[1];
            $seq = $matched[2];
            $playing_series = true;
            Logs::log("getMatchedAudio match series mode2, name:" . $name . ", seq:" . $seq);
        } else if (preg_match('#(.*)的歌$#isuU', $asr_result, $matched)) {
            $specified_singer = true;
            $name = $matched[1];
            Logs::log("getMatchedAudio match singer mode, name:" . $name);
        } else if (preg_match('#(.*)$#isuU', $asr_result, $matched)) {
            $name = $matched[1];
            Logs::log("getMatchedAudio match name:" . $name);
        }

        Logs::log("getMatchedAudio prepare to find match name:" . $name . " specified_singer:" . $specified_singer . " seq:" . $seq);

        if ($specified_singer) {
            $res = $this->getItemOfSinger($name);
            Logs::log("getMatchedAudio, got matched singer play url:" . $res);
        } else {
            $res = $this->getItemOfSpecified($name, $seq);
            Logs::log("getMatchedAudio, got matched play url:" . $res);
        }

        Logs::log("getMatchedAudio, no match found");
        return $res;
    }

    public function processPlayCommand($asr_result, $format) {
        $asr_result = $this->cleanString($asr_result);
        Logs::log("processPlayCommand:" . $asr_result);
        $res = false;

        if (preg_match("#(.*)" . $this->SPEECH_TEXT_PLAY_NEXT . "(.*)#isuU", $asr_result, $matched)) {
            $res = $this->getNext();
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_PLAY_PREVIOUS . "(.*)#isuU", $asr_result, $matched)) {
            $res = $this->getPrevious();
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_PLAY_RANDOM . "(.*)#isuU", $asr_result, $matched)) {
            $res = $this->getRandomAudio();
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_PLAY_FAVORITES . "(.*)#isuU", $asr_result, $matched)) {
            $res = $this->getFirstFavorite();
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_PLAY . "(.*)#isu", $asr_result, $matched)) {
            $res = $this->getMatchedAudio($matched[2]);
        }

        if ($res) {
            $this->setSemanticFormat($format);
            $this->addToHistory($this->current_audio);
        }

        Logs::log("processPlayCommand done:" . $asr_result . ", process result:" . $res);
        return $res;
    }

    public function processCtrlCommand($asr_result, $format) {
        $res = true;
        $asr_result = $this->cleanString($asr_result);
        Logs::log("processCtrlCommand:" . $asr_result); 
        if (preg_match("#(.*)" . $this->SPEECH_TEXT_REPEAT_WHAT_YOU_SAID . "(.*)#isuU", $asr_result, $matched)) {
            $this->speak($matched[2]);
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_REMOVE_FAVORITE . "(.*)#isuU", $asr_result, $matched)) {
            if (!empty($this->current_audio)) {
                $this->removeFavorite($this->current_audio);
                $this->speak("取消收藏成功。");
            } else {
                $this->speak("当前没有播放曲目哦。");
            }
        } else if (preg_match("#^" . $this->SPEECH_TEXT_ADD_FAVORITE . "(.*)#isuU", $asr_result, $matched)) {
            if (!empty($this->current_audio)) {
                $this->addFavorite($this->current_audio);
                $this->speak("收藏成功。");
            } else {
                $this->speak("当前没有播放曲目哦。");
            }
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_UPDATE_LIST . "(.*)#isuU", $asr_result, $matched)) {
            $this->updatePlayList();
        } else if (preg_match("#(.*)" . $this->SPEECH_TEXT_LIST_COMMANDS . "(.*)#isuU", $asr_result, $matched)) {
            $this->speak("我目前支持以下命令：");
            $this->speak(R1Configuration . SPEECH_TEXT_PLAY_RANDOM . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_ADD_FAVORITE . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_REMOVE_FAVORITE . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_PLAY_PREVIOUS . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_PLAY_NEXT . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_REPEAT_WHAT_YOU_SAID . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_UPDATE_LIST . "，");
            $this->speak(R1Configuration . SPEECH_TEXT_LIST_COMMANDS . "，");
        } else {
            $res = false;
        }
        return $res;
    }

    public function speak($text) {

    }

    private function setSemanticFormat($format) {
        Logs::log("setSemanticFormat, current_audio:" . $this->current_audio);
        if (empty($this->current_audio)) {
            return $format;
        }

        $play_list = $this->generatePlayList();
        $musiclist = new ItemList();
        if ($play_list !== false) {
            foreach ($play_list as $audio_item) {
                $name = $this->getNameFromItem($audio_item);
        
                $item = new Item();
                $item->setAlbum("");
        
                $item->setTitle($this->cleanString($this->getBaseName($audio_item)));
                $item->setArtist("本地");
                $item->setHdImgUrl("");
                $item->setLyric("");
                $item->setIsCollected(false);
                $item->setUrl($this->NAS_URL . $this->urlEncode($audio_item));
                $musiclist->addItem($item);

                Logs::log("setSemanticFormat, add play url:" . $this->NAS_URL . $this->urlEncode($audio_item));
            }
            
            $format->setPageSize(1);
            $format->setTotal(count($play_list));
            $format->setDataList($musiclist);
            $format->setText($name)->setAsrText($name);
            Logs::log("setSemanticFormat, count of play list item:" . count($play_list));
        }

        return $format;
    }
}

echo ("NasMedia loaded" . PHP_EOL);

/*
$nas = new NasMedia();
$nas->loadPlayList();
$nas->getMatchedAudio("播放下沙");
$nas->getNext();
$nas->getPrevious();
$nas->getRandomAudio();
 */
