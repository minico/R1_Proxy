<?php
namespace App\Util;

class Logs {
  private $log_path;

  function setLogPath($path) {
    $this->log_path = $path;
  }

  function log($str) {
    $str = $str . PHP_EOL;
    echo $str;
    file_put_contents($this->log_path, $str, FILE_APPEND);
  }
}