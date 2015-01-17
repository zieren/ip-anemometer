<?php
/* Common constants and functions.
 * TODO: Split this file up.
 */

require_once 'config.php';

function autoloader($class) {
  include 'classes/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('autoloader');

// Logger must be initialized before used in ipaFatalErrorHandler; see
// http://stackoverflow.com/questions/4242534/php-shutdown-cant-write-files
define('LOG_DIR', 'logs');
Logger::Instance();

function ipaErrorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
  Logger::Instance()->critical('Error '.$errno.': '.$errstr.' -- '.$errfile.':'.$errline);
  return false;  // continue with built-in error handling
}
set_error_handler('ipaErrorHandler');

function ipaFatalErrorHandler() {
  $error = error_get_last();
  if ($error && $error['type'] === E_ERROR) {
    Logger::Instance()->critical('Error: '.json_encode($error));
  }
}
register_shutdown_function('ipaFatalErrorHandler');

define('NOT_AVAILABLE', 'n/a');

// Internal use: Indexes in time series samples (before downsampling).
define('WIND_SAMPLE_START_TS', 0);
define('WIND_SAMPLE_END_TS', 1);
define('WIND_SAMPLE_AVG', 2);
define('WIND_SAMPLE_MAX', 3);

// Internal constants.
define('CLIENT_UPDATE_DIR', 'client');
define('CLIENT_UPDATE_FILENAME', 'client/ipa-update.zip');
define('DATE_FORMAT', 'Y-m-d H:i:s');  // timestamp format for MySQL and human readable output
// Maximum amount of time the desired window size is shifted back to compensate for upload
// latency. TODO: This (and possibly other values) should be configurable.
define('WIND_MAX_LATENCY', 15 * 60 * 1000);  // 15 minutes
define('CLIENT_UPDATE_MAX_SIZE', 1024 * 1024);  // 1MB

// Defaults and limits for request arguments.
define('REQ_WINDOW_MINUTES_DEFAULT', 60);
define('REQ_WINDOW_MINUTES_MAX', 24 * 60);
define('REQ_TIME_SERIES_POINTS_DEFAULT', 30);
define('REQ_SYSTEM_MINUTES', 24 * 60);

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
  return intval(round(microtime(true) * 1000));
}

function minutesToMillis($minutes) {
  return $minutes * 60 * 1000;
}
?>
