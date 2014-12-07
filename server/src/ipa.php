<?php
require_once 'common.php';

$windowMinutes = intval($_REQUEST[REQ_WINDOW_MINUTES]);
$timestamp = intval($_REQUEST[REQ_TIMESTAMP]);
if (!$windowMinutes) {  // omitted or zero
  $windowMinutes = REQ_WINDOW_MINUTES_DEFAULT;
}
$windowMinutes = min($windowMinutes, REQ_WINDOW_MINUTES_MAX);
if (!$timestamp) {
  $timestamp = timestamp();
}

$db = new Database();

// Apply settings.
$settings = $db->getAppSettings();
$logger = new Katzgrau\KLogger\Logger(LOG_DIR, getLogLevel($settings[LOG_LEVEL_KEY]));
if (isset($settings[LOG_LEVEL_KEY])) {
  $db->setLogLevel(getLogLevel($settings[LOG_LEVEL_KEY]));
}

$stats = $db->computeWindStatsAggregate($timestamp, minutesToMillis($windowMinutes));

if ($stats) {
  echo json_encode($stats);
} else {
  echo "!e";
}
?>