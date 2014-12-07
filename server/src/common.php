<?php
/* Common constants and functions.
 * TODO: Split this file up.
 */

require_once('config.php');

assert_options(ASSERT_BAIL, true);

function autoloader($class) {
  include 'classes/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('autoloader');

// ----- Client/server interaction. NOTE: These must match K.py -----
define('TIMESTAMP_KEY', 'ts');
define('META_KEY', 'meta');
define('TEMP_KEY', 'temp');
define('WIND_KEY', 'wind');
define('WIND_STARTUP_TIME_KEY', 'wind_startup');
define('WIND_UP_TO_TIME_KEY', 'wind_up_to');
define('WIND_REVOLUTIONS_KEY', 'wind_revs');
define('WIND_AGGREGATE_STATS_KEY', 'wind_stats');
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

// Wind stats keys. NOTE: Keep these in sync with wind_stats.py and ipa.js.
define('WIND_KEY_AVG', 0);
define('WIND_KEY_MAX', 1);
define('WIND_KEY_MAX_TS', 2);
define('WIND_KEY_HIST', 3);
define('WIND_KEY_START_TS', 4);
define('WIND_KEY_END_TS', 5);

// Keys for server settings.
define('LOG_LEVEL_KEY', 'logLevel');

// Internal constants.
define('CLIENT_UPDATE_FILENAME', 'client/ipa-update.zip');
define('DATE_FORMAT', 'Y-m-d H:i:s');  // timestamp format for MySQL and human readable output

// Request arguments.
define('REQ_WINDOW_MINUTES', 'm');
define('REQ_TIMESTAMP', 'ts');
define('REQ_WINDOW_MINUTES_DEFAULT', 60);
define('REQ_WINDOW_MINUTES_MAX', 24 * 60);

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

function formatTimestamp($timestamp) {
  return date(DATE_FORMAT, $timestamp / 1000);
}

function formatDuration($millis) {
  $seconds = $millis / 1000;
  return sprintf("%02d:%02d:%02d", floor($seconds / 3600), ($seconds / 60) % 60, $seconds % 60);
}

function durationToRps($duration) {
  return 1000 / $duration;
}

function timestamp() {
  return time() * 1000;
}

function minutesToMillis($minutes) {
  return $minutes * 60 * 1000;
}
?>
