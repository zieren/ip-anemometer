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

  // TODO: Handle stale data.
  $stats = array('wind' =>
      $db->computeWindStats($timestamp, minutesToMillis($windowMinutes), $timeSeriesPoints));
  if ($systemMinutes) {
    $systemMillis = minutesToMillis($systemMinutes);
    $stats['sys'] = array(
        'temp_t' => $db->readTemperature($timestamp, $systemMillis, $timeSeriesPoints),
        'strength_t' => $db->readSignalStrength($timestamp, $systemMillis, $timeSeriesPoints),
        'nwtype' => $db->readNetworkType($timestamp, $systemMillis),
        'traffic' => $db->readTransferVolume(),
        'lag' => $db->readLag($timestamp, $systemMillis, $timeSeriesPoints));
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