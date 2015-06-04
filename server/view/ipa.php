<?php
require_once '../common/common.php';

header("Access-Control-Allow-Origin: *");

function getIntParam($name, $default, $min, $max = PHP_INT_MAX) {
  $v = isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : $default;
  return max($min, min($v, $max));
}

function computeStats() {
  // TODO: These defaults should match the ones in ipa.js.
  // TODO: This isn't handling "not requested" properly.
  $windowMinutes = getIntParam('m', REQ_WINDOW_MINUTES_DEFAULT, 1, REQ_WINDOW_MINUTES_MAX);
  $timestamp = getIntParam('ts', timestamp(), 0);
  $timeSeriesPoints = getIntParam('p', REQ_TIME_SERIES_POINTS_DEFAULT, 1);
  $systemMinutes = getIntParam('s', REQ_SYSTEM_MINUTES, 0, REQ_SYSTEM_MINUTES_MAX);
  $doorStartMillis = getIntParam('d',
      $timestamp - daysToMillis(REQ_DOOR_DAYS),
      $timestamp - daysToMillis(REQ_DOOR_DAYS_MAX),
      $timestamp);
  $pilotsStartMillis = getIntParam('c',
    $timestamp - daysToMillis(REQ_PILOTS_DAYS),
    $timestamp - daysToMillis(REQ_PILOTS_DAYS_MAX),
    $timestamp);

  $db = new Database();

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
  $stats['door'] = $db->readDoor($doorStartMillis, $timestamp);
  $stats['pilots'] = $db->readPilots($pilotsStartMillis, $timestamp);
  return $stats;
}

try {
  $stats = computeStats();
} catch (Exception $e) {
  http_response_code(500);
}
echo json_encode($stats);
?>