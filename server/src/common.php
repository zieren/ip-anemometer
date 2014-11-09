<?php
/* Common constants and functions.
 */

require_once('config.php');

assert_options(ASSERT_BAIL, true);

function autoloader($class) {
  include 'classes/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('autoloader');

# ----- Client/server interaction. NOTE: These must match K.py -----
define('TIMESTAMP_KEY', 'ts');
define('VALUE_KEY', 'v');
define('META_KEY', 'meta');
define('TEMP_KEY', 'temp');
define('WIND_KEY', 'wind');
define('WIND_STARTUP_TIME_KEY', 'wind_startup');
define('WIND_UP_TO_TIME_KEY', 'wind_up_to');
define('WIND_VALUES_KEY', 'wind_values');
define('UPLOAD_KEY', 'upload');
define('CLIENT_MD5_KEY', 'md5');
define('STRATUM_KEY', 'stratum');
define('STARTUP_TIME_KEY', 'startup');
define('FAILED_UPLOADS_KEY', 'ulfs');
define('CLIENT_TIMESTAMP_KEY', 'cts');
define('UPLOAD_POST_KEY', 'bz2');
define('COMMAND_RESTART', 'restart');
define('RESPONSE_STATUS', 'status');
define('RESPONSE_STATUS_OK', 'ok');

// Keys for server settings.
define('LOG_LEVEL_KEY', 'logLevel');

// Internal constants.
define('CLIENT_UPDATE_FILENAME', 'client/ipa-update.zip');
define('CLIENT_UPDATE_MAX_SIZE', 1024 * 1024);  // 1MB
define('DATE_FORMAT', 'Y-m-d H:i:s');  // MySQL timestamp format


use Psr\Log\LogLevel;
$LOG_LEVELS = array(
  'emergency' => LogLevel::EMERGENCY,
  'alert' => LogLevel::ALERT,
  'critical' => LogLevel::CRITICAL,
  'error' => LogLevel::ERROR,
  'warning' => LogLevel::WARNING,
  'notice' => LogLevel::NOTICE,
  'info' => LogLevel::INFO,
  'debug' => LogLevel::DEBUG
);

// TODO: Should this warn about invalid levels?
/** Returns the specified log level as a Psr\Log\LogLevel. */
function getLogLevel($level) {
  global $LOG_LEVELS;
  if (isset($LOG_LEVELS[$level])) {
    return $LOG_LEVELS[$level];
  }
  return LogLevel::DEBUG;  // default or invalid
}

/** Returns the current path, e.g. for "http://foo.bar/baz/qux.php" -> "http://foo.bar/baz/". */
function getCurentPagePathURL() {
  $pageURL = 'http';
  if ($_SERVER['HTTPS'] == 'on') {
    $pageURL .= 's';
  }
  $pageURL .= '://';
  if ($_SERVER['SERVER_PORT'] != '80') {
    $pageURL .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
  } else {
    $pageURL .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
  }
  $lastSlash = strrpos($pageURL, '/');
  return substr($pageURL, 0, $lastSlash + 1);
}

function formatTimestamp($timestampSeconds) {
  return date(DATE_FORMAT, $timestampSeconds);
}

function formatDuration($seconds) {
  return sprintf("%02d:%02d:%02d", floor($seconds / 3600), ($seconds / 60) % 60, $seconds % 60);
}

?>
