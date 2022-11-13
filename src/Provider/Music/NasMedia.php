<?php
namespace App\Provider\Music;

class NasMedia
{
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

    //public static $NAS_URL = "http://192.168.1.4/music/";
    private $NAS_URL = "http://10.0.0.4:8888/music/";
    //private $ROOT_PATH = "../../" . dirname(__FILE__);
    private $PLAY_LIST_FILE = "play_list.txt";
    private $HISTORY_LIST_FILE = "history_list.txt";
    private $FAVORITE_LIST_FILE = "favorite_list.txt";

    private $media_list = array();
    private $favorite_list = array();
    private $history_list = array(); // 用于保存序列故事，以便下次顺序播放
    private $current_audio = "";
    private $playing_favorite = false;
    private $playing_series = false;

    public function __construct()
    {
        $this->loadPlayList();
        $this->loadHistory();
        $this->loadFavorite();
    }

    private function getRootPath()
    {
        return dirname(__FILE__) . "/../../../";
    }

    private function cleanString($str)
    {
        echo "cleanString:" . $str . PHP_EOL;
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

    private function getBaseName($file_path)
    {
        $file_name = preg_replace('/^.+[\\\\\\/]/', '', $file_path);
        $base_name = str_replace(strrchr($file_name, "."), "", $file_name);
        return $base_name;
    }

    private function isChineseNum($num)
    {
        return !preg_match('/^[0-9]+$/', $num);
    }

    private function chineseNumToDigits($str)
    {
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

    private function getNameFromItem($item)
    {
        echo ("getNameFromItem:" . $item . PHP_EOL);
        if (preg_match('/[\x{4e00}-\x{9fa5}]+/u', $item, $matched)) {
            $name = $matched[0];
            return $name;
        }
        return false;
    }

    private function getSeqFromItem($item)
    {
        if (preg_match('/(0*)([0-9]+)/u', $item, $matched)) {
            $seq = $matched[2];
            if ($seq < 1000) { //忽略一些年份信息的数字，比如2009，2019等
                echo ("getSeqFromItem:" . $item . " seq:" . $seq . PHP_EOL);
                return $seq;
            }
        }
        echo ("getSeqFromItem:" . $item . ", no seq found, return false" . PHP_EOL);
        return false;
    }

    public function loadPlayList()
    {
        $this->media_list = array();
        $res = file_get_contents($this->NAS_URL . $this->PLAY_LIST_FILE);
        if ($res) {
            $this->media_list = explode("\n", $res);
            file_put_contents($this->getRootPath() . $this->PLAY_LIST_FILE, $res);
            echo "play list downloaded successfully, total count:" . count($this->media_list) . "\n";
            return true;
        } else {
            echo "play list downloading failed.\n";
            return false;
        }
    }

    public function loadHistory()
    {
        $res = file_get_contents($this->getRootPath() . $this->HISTORY_LIST_FILE);
        if ($res) {
            $this->history_list = explode("\n", $res);
            echo "history list loaded successfully, total count:" . count($this->history_list) . "\n";
            return true;
        } else {
            echo "history list load failed.\n";
            return false;
        }
    }

    public function saveHistory()
    {
        file_put_contents($this->getRootPath() . $this->HISTORY_LIST_FILE, implode(PHP_EOL, $this->history_list));
    }

    public function addToHistory($item)
    {
        $name = $this->getNameFromItem($item);
        foreach ($this->history_list as $key => $value) {
            if (strstr($value, $name)) {
                unset($this->history_list[$key]);
            }
        }
        array_push($this->history_list, $item);
        $this->saveHistory();
    }

    public function loadFavorite()
    {
        $res = file_get_contents($this->getRootPath() . $this->FAVORITE_LIST_FILE);
        if ($res) {
            $this->favorite_list = explode("\n", $res);
            echo "favorite list loaded successfully, total count:" . count($this->favorite_list) . "\n";
            return true;
        } else {
            echo "favorte list load failed.\n";
            return false;
        }
    }

    public function saveFavorite()
    {
        file_put_contents($this->getRootPath() . $this->FAVORITE_LIST_FILE, implode(PHP_EOL, $this->favorite_list));
    }

    public function addFavorite($item)
    {
        echo "addFavorite:" . $item . PHP_EOL;
        if (array_search($item, $this->favorite_list) === false) {
            array_push($this->favorite_list, $item);
            $this->saveFavorite();
        } else {
            echo "addFavorite:" . $item . " has been existed" . PHP_EOL;
        }
    }

    public function removeFavorite($item)
    {
        echo "removeFavorite:" . $item . PHP_EOL;
        foreach (array_keys($this->favorite_list, $item) as $key) {
            unset($array[$key]);
        }
        $this->saveFavorite();
    }

    public function getNext()
    {
        echo ("getNext, current_audio:" . $this->current_audio . " playing_series:" . $this->playing_series . " palying_favorite:" . $this->playing_favorite . PHP_EOL);
        if ($this->playing_series) {
            $name = $this->getNameFromItem($this->current_audio);
            $seq = $this->getSeqFromItem($this->current_audio);
            if ($seq !== false) {
                $seq += 1;
            } else {
                $seq = "";
            }
            return $this->getItemOfSpecified($name, $seq);
        }

        $tmpList = $this->media_list;
        if ($this->playing_favorite) {
            $tmpList = $this->favorite_list;
        }
        echo ("getNext, list count:" . count($tmpList) . PHP_EOL);
        $key = array_search($this->current_audio, $tmpList);
        if ($key !== false) {
            if ($key < 0 || $key + 1 == count($tmpList)) {
                $key = -1;
            }
            $this->current_audio = $tmpList[$key + 1];
            echo ("getNext, return current_audio:" . $this->current_audio . PHP_EOL);
            return $this->NAS_URL . $this->current_audio;
        }
        return false;
    }

    public function getPrevious()
    {
        echo ("getPrevious, current_audio:" . $this->current_audio . " playing_series:" . $this->playing_series . " palying_favorite:" . $this->playing_favorite . PHP_EOL);
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
            return $this->getItemOfSpecified($name, $seq);
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
            echo ("getPrevious, return current_audio:" . $this->current_audio . PHP_EOL);
            return $this->NAS_URL . $this->current_audio;
        }
        return false;
    }

    public function getRandomAudio()
    {
        $this->current_audio = $this->media_list[rand(0, count($this->media_list))];
        echo ("getRandomAudio, current_audio:" . $this->current_audio . PHP_EOL);
        return $this->NAS_URL . $this->current_audio;
    }

    public function getFirstFavorite()
    {
        if (count($this->favorite_list) > 0) {
            $this->playing_favorite = true;
            $this->current_audio = $this->favorite_list[0];
            echo ("getFirstFavorite, current_audio:" . $this->current_audio . PHP_EOL);
            return $this->NAS_URL . $this->current_audio;
        }
        return false;
    }

    private function getItemOfSinger($singer)
    {
        echo "getItemOfSinger:" . $singer . "media list count:" . count($this->media_list) . PHP_EOL;
        $singerList = array();
        foreach ($this->media_list as $item) {
            if (strstr($item, $singer)) {
                array_push($singerList, $item);
            }
        }
        echo "getItemOfSinger count:" . count($singerList) . PHP_EOL;

        if (count($singerList) > 0) {
            $this->current_audio = $singerList[rand(0, count($singerList) - 1)];
            echo "getItemOfSinger found:" . $this->current_audio . PHP_EOL;
            return $this->NAS_URL . $this->current_audio;
        }

        echo "getItemOfSinger, no item found" . PHP_EOL;
        return false;
    }

    private function getItemOfSpecified($name, $seq)
    {
        echo ("getItemOfSpecified, name:" . $name . " seq:" . $seq . PHP_EOL);
        foreach ($this->history_list as $item) {
            if (empty($seq) && !empty($name) && strstr($item, $name)) {
                $name = $this->getNameFromItem($item);
                $seq = $this->getSeqFromItem($item);
                if ($seq !== false) {
                    $seq += 1;
                } else {
                    $seq = "";
                }
            }
        }

        echo ("getItemOfSpecified, new name:" . $name . "new seq:" . $seq . PHP_EOL);

        foreach ($this->media_list as $item) {
            if (preg_match('#(.*)' . $name . '(.*)0*' . $seq . '(.*)#isuU', $item, $matched)) {
                $this->current_audio = $item;
                echo ("found item:" . $item . "\n");
                if ($seq !== false) {
                    $this->playing_series = true;
                }
                return $this->NAS_URL . $this->current_audio;
            }
        }
        echo "getItemOfSpecified, no item found" . PHP_EOL;
        return false;
    }

    public function getMatchedAudio($asr_result)
    {
        $name = "";
        $seq = "";
        $specified_singer = false;
        $this->playing_favorite = false;
        $this->playing_series = false;
        $this->current_audio = "";

        echo "getMatchedAudio prepare to find match text:" . $asr_result . PHP_EOL;
        if (preg_match('#(.+)第(.*)集$#isuU', $asr_result, $matched)) {
            $name = $matched[1];
            $seq = $matched[2];
            if ($this->isChineseNum($seq)) {
                $seq = $this->chineseNumToDigits($seq);
            }
            $playing_series = true;
        } else if (preg_match('#([\x{4e00}-\x{9fa5}]+)([0-9]+)#isu', $asr_result, $matched)) {
            $name = $matched[1];
            $seq = $matched[2];
            $playing_series = true;
        } else if (preg_match('#(.*)的歌$#isuU', $asr_result, $matched)) {
            $specified_singer = true;
            $name = $matched[1];
        } else if (preg_match('#(.*)$#isuU', $asr_result, $matched)) {
            $name = $matched[1];
        }

        echo ("getMatchedAudio prepare to find match name:" . $name . " specified_singer:" . $specified_singer . " seq:" . $seq . PHP_EOL);

        if ($specified_singer) {
            return $this->getItemOfSinger($name);
        } else {
            return $this->getItemOfSpecified($name, $seq);
        }

        echo "not found\n";
        return false;
    }

    public function processPlayCommand($asr_result, $format)
    {
        $asr_result = $this->cleanString($asr_result);
        echo ("processPlayCommand:" . $asr_result . PHP_EOL);
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

        return $res;
    }

    public function processCtrlCommand($asr_result, $format)
    {
        $res = true;
        $asr_result = $this->cleanString($asr_result);
        echo ("processCtrlCommand:" . $asr_result . PHP_EOL);
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

    public function speak($text)
    {

    }

    private function setSemanticFormat($format)
    {
        echo "setSemanticFormat, current_audio:" . $this->current_audio . PHP_EOL;
        if (empty($this->current_audio)) {
            return $format;
        }

        $name = $this->getNameFromItem($this->current_audio);
        $seq = $this->getSeqFromItem($this->current_audio);

        $item = new Item();
        $item->setAlbum("");

        $item->setTitle($this->cleanString($this->getBaseName($this->current_audio)));
        $item->setArtist("本地");
        $item->setHdImgUrl("");
        $item->setLyric("");
        $item->setIsCollected(false);
        $item->setUrl($this->NAS_URL . $this->current_audio);

        $musiclist = new ItemList();
        $musiclist->setDataList($item);

        $format->setPageSize(1);
        $format->setTotal(1);
        $format->setDataList($musiclist);
        $format->setText($name)->setAsrText($name);
        return $format;
    }
}

echo ("pass" . PHP_EOL);

/*
$nas = new NasMedia();
$nas->loadPlayList();
$nas->getMatchedAudio("播放下沙");
$nas->getNext();
$nas->getPrevious();
$nas->getRandomAudio();
 */
