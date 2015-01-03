<?php
require_once 'common.php';

final class Logger {
  public static function Instance() {
    static $instance = null;
    if ($instance === null) {
      $instance = new Katzgrau\KLogger\Logger(LOG_DIR);
    }
    return $instance;
  }

  private function __construct() {}
}
?>
