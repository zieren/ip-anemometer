<?php
/* Common constants and functions.
 * TODO: Split this file up.
 */

require_once 'config.php';

function autoloader($class) {
  // TODO: This is silly because it breaks when requests aren't exactly one directory deep.
  include '../common/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('autoloader');

// Logger must be initialized before used in ipaFatalErrorHandler; see
// http://stackoverflow.com/questions/4242534/php-shutdown-cant-write-files
define('LOG_DIR', '../logs');
Logger::Instance();

// TODO: Log request content in error handlers?

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

define('IPA_GREETING', 'IP anemometer server 0.0.6');

define('NOT_AVAILABLE', 'n/a');

// Internal use: Indexes in time series samples (before downsampling).
define('WIND_SAMPLE_START_TS', 0);
define('WIND_SAMPLE_END_TS', 1);
define('WIND_SAMPLE_AVG', 2);
define('WIND_SAMPLE_MAX', 3);

// Internal constants.
define('CLIENT_APP_ZIP_FILENAME', '../client/ipa-update.zip');
define('CLIENT_APP_CFG_FILENAME', 'ipa.cfg');  // filename inside .zip
define('CLIENT_APP_CFG_DEFAULT_FILENAME', '../client/ipa-default.cfg');
define('DATE_FORMAT', 'Y-m-d H:i:s');  // timestamp format for MySQL and human readable output
// Maximum amount of time the desired window size is shifted back to compensate for upload
// latency. TODO: This (and possibly other values) should be configurable.
define('WIND_MAX_LATENCY', 15 * 60 * 1000);  // 15 minutes
define('CLIENT_APP_MAX_SIZE', 1024 * 1024);  // 1MB

// Defaults and limits for request arguments.
define('REQ_WINDOW_MINUTES_DEFAULT', 60);
define('REQ_WINDOW_MINUTES_MAX', 24 * 60);
define('REQ_TIME_SERIES_POINTS_DEFAULT', 30);
define('REQ_SYSTEM_MINUTES', 24 * 60);
define('REQ_SYSTEM_MINUTES_MAX', 7 * 24 * 60);
define('REQ_DOOR_DAYS', 9);
define('REQ_DOOR_DAYS_MAX', 31);

/** Returns the absolute URL of the specified relative path. */
function getAbsoluteURL($relativePath) {
  $url = 'http';
  if (get($_SERVER['HTTPS'], '') == 'on') {
    $url .= 's';
  }
  $url .= '://';
  if ($_SERVER['SERVER_PORT'] != '80') {
    $url .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
  } else {
    $url .= $_SERVER['SERVER_NAME'];
  }
  $lastSlash = strrpos($_SERVER['REQUEST_URI'], '/');  // always starts with a slash
  $path = substr($_SERVER['REQUEST_URI'], 0, $lastSlash + 1).$relativePath;
  $url .= executeRelativePathComponents($path);
  return $url;
}

/** Converts '/a//b/./x/../c/' -> '/a/b/c' */
function executeRelativePathComponents($path) {
  $in = explode('/', $path);
  $out = [];
  foreach ($in as $c) {
    switch ($c) {
  	  case '.':
  	    break;
  	  case '..':
  	    array_pop($out);
  	    break;
  	  case '':  // ignore empty component except from leading slash
  	    if (!$out) {
  	      $out[] = $c;
  	    }
  	    break;
  	  default:
  	    $out[] = $c;
    }
  }
  if (count($out) === 1 && $out[0] === '') {
    $out[] = '';  // special case: add '' to implode to '/' (instad of '')
  }
  return implode('/', $out);
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

function daysToMillis($days) {
  return $days * 24 * 60 * 60 * 1000;
}

function get(&$value, $default=null) {
    return isset($value) ? $value : $default;
}

// TODO: This is slightly misplaced in common.php.
// TODO: This should probably make a copy of the file and move it back to avoid races.
// TODO: Would be nice to avoid timestamps (of ipa.cfg - and the operation itself?) in the .zip,
// since they affect the md5 and result in an unnecessary download. Use touch()?
/** Builds the client app .zip by adding the client config from $db to CLIENT_APP_ZIP_FILENAME. */
function buildClientAppZip($db) {
  $zip = new ZipArchive();
  $retval = $zip->open(CLIENT_APP_ZIP_FILENAME, ZipArchive::CREATE);
  if ($retval !== true) {
    throw new Exception('failed to open '.CLIENT_APP_ZIP_FILENAME.': '.$retval);
  }
  $zip->deleteName(CLIENT_APP_CFG_FILENAME);  // may or may not be present
  $ok = $zip->addFromString(CLIENT_APP_CFG_FILENAME, $db->createClientConfigFile())
      && $zip->close();
  if (!$ok) {
    throw new Exception('failed to add '.CLIENT_APP_CFG_FILENAME.' to '.CLIENT_APP_ZIP_FILENAME);
  }
  $md5 = md5_file(CLIENT_APP_ZIP_FILENAME);
  if ($md5) {
    $db->setConfig('s:client_md5', $md5);
  } else {
    $db->clearConfig('s:client_md5');
  }
}
?>
