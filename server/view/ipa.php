<?php
require_once '../common/common.php';

header("Access-Control-Allow-Origin: *");

function getNumParam($name, $default, $min, $max) {
  if (!isset($_REQUEST[$name])) {
    return $default;
  }
  return max($min, min($_REQUEST[$name], $max));
}

function getStringParam($name, $default) {
  return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}

function computeStats() {
  // TODO: These defaults should match the ones in ipa.js.
  // TODO: This isn't handling "not requested" properly.
  // TODO: This needs to be simplified.
  // TODO: This should sanitize the input.
  $windowMinutes = getNumParam('m', REQ_WINDOW_MINUTES_DEFAULT, 1, REQ_WINDOW_MINUTES_MAX);
  $timestamp = getNumParam('ts', timestamp(), 0, timestamp());
  $timeSeriesPoints = getNumParam('p', REQ_TIME_SERIES_POINTS_DEFAULT, REQ_TIME_SERIES_POINTS_MIN,
      REQ_TIME_SERIES_POINTS_MAX);
  $systemMinutes = getNumParam('s', REQ_SYSTEM_MINUTES, 0, REQ_SYSTEM_MINUTES_MAX);
  $doorStartMillis = getNumParam('d',
      $timestamp - daysToMillis(REQ_DOOR_DAYS),
      $timestamp - daysToMillis(REQ_DOOR_DAYS_MAX),
      $timestamp);
  $pilotsStartMillis = getNumParam('pc',  // pc = pilot count
      $timestamp - daysToMillis(REQ_PILOTS_DAYS),
      $timestamp - daysToMillis(REQ_PILOTS_DAYS_MAX),
      $timestamp);

  $db = new Database();

  $stats = array('wind' =>
      $db->computeWindStats($timestamp, minutesToMillis($windowMinutes), $timeSeriesPoints));
  $stats['status'] = $db->readStatus();
  if ($systemMinutes) {
    $systemMillis = minutesToMillis($systemMinutes);
    $stats['sys'] = array(
        'temp_t' => $db->readTemperature($timestamp, $systemMillis, $timeSeriesPoints),
        'strength_t' => $db->readSignalStrength($timestamp, $systemMillis, $timeSeriesPoints),
        'nwtype' => $db->readNetworkType($timestamp, $systemMillis),
        'traffic' => $db->readTransferVolume(),
        'lag' => $db->readLag($timestamp, $systemMillis, $timeSeriesPoints));
  }
  // TODO: Don't use $systemMillis. Should this be the same as the wind interval, or 24h?
  $stats['temp_hum'] = $db->readTempHum($timestamp, $systemMillis, $timeSeriesPoints);
  $stats['door'] = $db->readDoor($doorStartMillis, $timestamp);
  $stats['pilots'] = $db->readPilots($pilotsStartMillis, $timestamp);
  $stats['adc'] = $db->readAdcValues($timestamp, $systemMillis, $timeSeriesPoints);
  return $stats;
}

try {
  $stats = computeStats();
} catch (Exception $e) {
  http_response_code(500);
}
echo json_encode($stats);
?>