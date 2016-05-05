<?php
/* Common constants and functions.
 * TODO: Split this file up.
 */

// Timestamps are in millis and currently have 13 digits. Typical default precision is 12.
ini_set('precision', 14);

// Internal constants.
define('PHP_MIN_VERSION', '5.3');
define('LOG_DIR', '../logs');
define('LOG_PATTERN', '/log_(\d\d\d\d)-(\d\d)-(\d\d)\.txt/');  // for pruning log files
define('CONFIG_PHP', '../common/config.php');
define('CLIENT_APP_ZIP_FILENAME', '../client/ipa-client.zip');
define('CLIENT_APP_CFG_FILENAME', 'ipa.cfg');  // filename inside .zip
define('CONFIG_DEFAULT_FILENAME', '../admin/default.cfg');
// Maximum amount of time the desired window size is shifted back to compensate for upload
// latency. TODO: This (and possibly other values) should be configurable.
define('WIND_MAX_LATENCY', 15 * 60 * 1000);  // 15 minutes
define('CLIENT_APP_MAX_SIZE', 1024 * 1024);  // 1MB

function autoloader($class) {
  // TODO: This is silly because it breaks when requests aren't exactly one directory deep.
  include '../common/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('autoloader');

function checkRequirements() {
  $unmet = array();
  if (version_compare(PHP_VERSION, PHP_MIN_VERSION) < 0) {
    $unmet[] = 'PHP version '.PHP_MIN_VERSION.' is required, but this is '.PHP_VERSION.'.';
  }
  foreach (array(LOG_DIR, dirname(CLIENT_APP_ZIP_FILENAME), CLIENT_APP_ZIP_FILENAME) as $file) {
    if (!file_exists($file)) {
      $unmet[] = $file.' is missing.';
      continue;
    }
    if (!is_writable($file)) {
      $what = is_file($file) ? 'file' : 'directory';
      $unmet[] = 'Make the '.$what.' "'.$file.'" writable by the PHP user.';
    }
  }
  if (!is_readable(CONFIG_PHP)) {
    $unmet[] = 'Copy the file ../common/config-sample.php to '.CONFIG_PHP.' and edit it.';
  }
  if (!function_exists('mysqli_connect')) {
    $unmet[] = 'The mysqli extension is missing (try apt-get install php5-mysql).';
  }
  if (!$unmet) {
    return;
  }
  echo '<p><b>Please follow these steps to complete the installation:</b></p>'
      .'<ul><li>'.implode('</li><li>', $unmet).'</li></ul><hr />';
  throw new Exception(implode($unmet));
}

checkRequirements();

require_once CONFIG_PHP;

// Logger must be initialized before used in ipaFatalErrorHandler; see
// http://stackoverflow.com/questions/4242534/php-shutdown-cant-write-files
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

define('IPA_SERVER_HEADING', 'IP anemometer server 0.3.1');

define('NOT_AVAILABLE', 'n/a');

// Internal use: Indexes in time series samples (before downsampling).
define('WIND_SAMPLE_START_TS', 0);
define('WIND_SAMPLE_END_TS', 1);
define('WIND_SAMPLE_AVG', 2);
define('WIND_SAMPLE_MAX', 3);

// Defaults and limits for request arguments.
define('REQ_WINDOW_MINUTES_DEFAULT', 60);
define('REQ_WINDOW_MINUTES_MAX', 24 * 60);
define('REQ_TIME_SERIES_POINTS_DEFAULT', 30);
define('REQ_TIME_SERIES_POINTS_MIN', 1);
define('REQ_TIME_SERIES_POINTS_MAX', 10000);  // a really wide screen
define('REQ_SYSTEM_MINUTES', 24 * 60);
define('REQ_SYSTEM_MINUTES_MAX', 7 * 24 * 60);
define('REQ_DOOR_DAYS', 9);
define('REQ_DOOR_DAYS_MAX', 31);
define('REQ_PILOTS_DAYS', 9);
define('REQ_PILOTS_DAYS_MAX', 31);

/** Converts '/a//b/./x/../c/' -> '/a/b/c' */
function executeRelativePathComponents($path) {
  $in = explode('/', $path);
  $out = array();
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

/** Returns epoch time in millis. */
function timestamp() {
  return round(microtime(true) * 1000);
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

/**
 * Convert the argument to a string suitable as a key in an array. This is for 32bit PHP (e.g. on
 * the Pi): Large numbers (e.g. timestamps in millis) are represented as floats when they exceed the
 * integer limit. We need a unique mapping of these to strings to use them as array keys. But the
 * built-in conversion sometimes uses scientific notation (e.g. 1000 = 1e3, but "1000" != "1e3"), so
 * make sure to always use standard notation.
 */
function tokey($arg) {
  return sprintf('%.0F', round($arg));
}

// TODO: This is slightly misplaced in common.php.
// TODO: This should probably make a copy of the file and move it back to avoid races.
/**
 * Builds the client app .zip by adding the client config from $db to CLIENT_APP_ZIP_FILENAME.
 * Updates the client_version in the config first, to be included in the .zip.
 */
function buildClientAppZip($db) {
  $db->setConfig('c:client_version', timestamp());
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
}
?>
