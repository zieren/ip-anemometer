<?php
require_once 'common.php';

function computeStats() {
  // TODO: These defaults should match the ones in ipa.js.
  $windowMinutes = intval($_REQUEST[REQ_WINDOW_MINUTES]);
  if (!$windowMinutes) {  // omitted or zero
    $windowMinutes = REQ_WINDOW_MINUTES_DEFAULT;
  }
  $windowMinutes = min($windowMinutes, REQ_WINDOW_MINUTES_MAX);
  $timestamp = intval($_REQUEST[REQ_TIMESTAMP]);
  if (!$timestamp) {
    $timestamp = timestamp();
  }
  $timeSeriesPoints = intval($_REQUEST[REQ_TIME_SERIES_POINTS]);
  if (!$timeSeriesPoints) {
    $timeSeriesPoints = REQ_TIME_SERIES_POINTS_DEFAULT;
  }
  $systemMinutes = intval($_REQUEST[REQ_SYSTEM_MINUTES]);

  $db = new Database();
  // Apply settings.
  // TODO: Factor this out.
  $settings = $db->getAppSettings();
  $logger = new Katzgrau\KLogger\Logger(LOG_DIR, getLogLevel($settings[LOG_LEVEL_KEY]));
  if (isset($settings[LOG_LEVEL_KEY])) {
    $db->setLogLevel(getLogLevel($settings[LOG_LEVEL_KEY]));
  }

  $stats = $db->computeWindStats(
      $timestamp, minutesToMillis($windowMinutes), $timeSeriesPoints);
  // TODO: Handle stale data.
  if (!$stats) {
    return NULL;  // TODO: Handle absent values per type, not globally.
  }
  if ($systemMinutes) {
    $systemMillis = minutesToMillis($systemMinutes);
    $stats[TEMP_KEY_TIME_SERIES] = $db->readTemperature(
        $timestamp, $systemMillis, $timeSeriesPoints);
    $stats[LINK_STRENGTH_KEY_TIME_SERIES] = $db->readSignalStrength(
        $timestamp, minutesToMillis($systemMinutes), $timeSeriesPoints);
    $stats[LINK_NW_TYPE_KEY_2] = $db->readNetworkType($timestamp, $systemMillis);
    $stats[LINK_UL_DL_KEY] = $db->readTransferVolume();
  }
  return $stats;
}

/** Create inconsistent random dummy stats for testing. */
function createDummyStats() {
  $timestamp = timestamp() - 30 * 1000;

  $stats[WIND_KEY_AVG] = max(0, rand(-20, 150)) / 10;
  $stats[WIND_KEY_MAX] = $stats[WIND_KEY_AVG] * rand(10, 30) / 10;
  $stats[WIND_KEY_MAX_TS] = $timestamp - 3 * 60 * 1000;
  $stats[WIND_KEY_START_TS] = $timestamp - 10 * 60 * 1000;
  $stats[WIND_KEY_END_TS] = $timestamp;
  $hist = array();
  $sum = 0;
  $numBins = max(1, rand(-5, 30));
  $skipBin = rand(0, $numBins * 1.2);
  for ($i = 0; $i < $numBins; ++$i) {
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
  echo RESPONSE_NO_STATS;
}
?>