<?php
require_once 'common.php';

function getIntParam($name, $default, $min, $max = PHP_INT_MAX) {
  $v = isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : $default;
  return max($min, min($v, $max));
}

function computeStats() {
  // TODO: These defaults should match the ones in ipa.js.
  $windowMinutes = getIntParam('m', REQ_WINDOW_MINUTES_DEFAULT, 1, REQ_WINDOW_MINUTES_MAX);
  $timestamp = getIntParam('ts', timestamp(), 0);
  $timeSeriesPoints = getIntParam('p', REQ_TIME_SERIES_POINTS_DEFAULT, 1);
  $systemMinutes = getIntParam('s', REQ_SYSTEM_MINUTES, 0);

  $db = new Database();

  $stats = $db->computeWindStats($timestamp, minutesToMillis($windowMinutes), $timeSeriesPoints);
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
    $stats['lag'] = $db->readLag($timestamp, $systemMillis);
  }
  return $stats;
}

$stats = computeStats();

if ($stats) {
  echo json_encode($stats);
} else {
  echo RESPONSE_NO_STATS;
}
?>