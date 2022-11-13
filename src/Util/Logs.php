<?php
namespace App\Util;

use \Datetime;

class Logs
{
    private static $log_path;

    public static function setLogPath($path)
    {
        echo "set log path:" . $path . PHP_EOL;
        self::$log_path = $path;
    }

    public static function log($str)
    {
        $date = new DateTime();
        $date = $date->format("m-d h:i:s.u");
        $date = substr($date, 0, -3); // 毫秒只保留三位
        $str = "[" . $date . "] " . $str . PHP_EOL;

        echo $str;
        file_put_contents(self::$log_path, $str, FILE_APPEND);
    }
}
