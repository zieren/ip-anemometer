<?php
require_once 'common.php';

function computeStats() {
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
  // TODO: Factor this out.
  $settings = $db->getAppSettings();
  $logger = new Katzgrau\KLogger\Logger(LOG_DIR, getLogLevel($settings[LOG_LEVEL_KEY]));
  if (isset($settings[LOG_LEVEL_KEY])) {
    $db->setLogLevel(getLogLevel($settings[LOG_LEVEL_KEY]));
  }

  return $db->computeWindStatsAggregate($timestamp, minutesToMillis($windowMinutes));
}

function createDummyStats() {
  $timestamp = timestamp() - 30 * 1000;

  $stats[WIND_KEY_AVG] = max(0, rand(-20, 150)) / 10;
  $stats[WIND_KEY_MAX] = $stats[WIND_KEY_AVG] * rand(10, 30) / 10;
  $stats[WIND_KEY_MAX_TS] = $timestamp - 3 * 60 * 1000;
  $stats[WIND_KEY_START_TS] = $timestamp - 10 * 60 * 1000;
  $stats[WIND_KEY_END_TS] = $timestamp;
  $hist = array();
  $sum = 0;
  $numBins = rand(1, 30);
  $skipBin = rand(0, $numBins * 1.2);
  for ($i = 0; $i <= $numBins; ++$i) {
    if ($i == $skipBin) {
      continue;
    }
    $x = rand(0, 1000);
    $hist[$i] = $x;
    $sum += $x;
  }
  foreach ($hist as $kmh => $x) {
    $hist[$kmh] = $x / $sum;
  }
  $stats[WIND_KEY_HIST] = $hist;

  $stats[WIND_KEY_TIME_SERIES] = array();
  for ($i = 0; $i < 10; ++$i) {
    $avg = rand(0, $stats[WIND_KEY_MAX] * 10) / 10;
    $stats[WIND_KEY_TIME_SERIES][] = array(
      $timestamp - (10 + $i) * 60 * 1000,
      $avg,
      rand($avg * 10, $stats[WIND_KEY_MAX] * 10) / 10);
  }

  return $stats;
}

if ($_REQUEST[REQ_DUMMY]) {
  $stats = createDummyStats();
} else {
  $stats = computeStats();
}

if ($stats) {
  echo json_encode($stats);
} else {
  echo "n/a";
}
?>