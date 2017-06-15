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
  // TODO: This should sanitize the input.

  $db = new Database();
  $config = $db->getConfig();
  $timeSeriesMaxMinutes = $config['s:query_time_series_max_minutes'];
  $eventMaxMinutes = $config['s:query_event_max_minutes'];

  $timestampNow = timestamp();
  $endTimestamp = getNumParam('upto', $timestampNow, 0, $timestampNow);
  $maxSamples = getNumParam('samples', REQ_TIME_SERIES_POINTS_DEFAULT, REQ_TIME_SERIES_POINTS_MIN,
      REQ_TIME_SERIES_POINTS_MAX);
  $result = array('status' => $db->readStatus());

  // Wind
  $windowDuration = minutesToMillis(getNumParam('wind', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['wind'] = $db->computeWindStats($endTimestamp, $windowDuration, $maxSamples);
  }
  // Temperature and humidity
  $windowDuration = minutesToMillis(getNumParam('tempHum', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['temp_hum'] = $db->readTempHum($endTimestamp, $windowDuration, $maxSamples);
  }
  // ADC. TODO: Handle ADC channels individually?
  $windowDuration = minutesToMillis(getNumParam('adc', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['adc'] = $db->readAdcValues($endTimestamp, $windowDuration, $maxSamples);
  }
  // Door
  $startTimestamp =
      getNumParam('door', 0, $endTimestamp - minutesToMillis($eventMaxMinutes), $endTimestamp);
  if ($startTimestamp > 0) {
    $result['door'] = $db->readDoor($startTimestamp, $endTimestamp);
  }
  // Pilots
  $startTimestamp =
      getNumParam('pilots', 0, $endTimestamp - minutesToMillis($eventMaxMinutes), $endTimestamp);
  if ($startTimestamp > 0) {
    $result['pilots'] = $db->readPilots($startTimestamp, $endTimestamp);
  }
  // CPU temperature.
  $windowDuration = minutesToMillis(getNumParam('cpuTemp', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['cpu_temp'] = $db->readTemperature($endTimestamp, $windowDuration, $maxSamples);
  }
  // Signal strength.
  $windowDuration = minutesToMillis(getNumParam('signal', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['signal_strength'] = $db->readSignalStrength($endTimestamp, $windowDuration, $maxSamples);
  }
  // Network type.
  $windowDuration = minutesToMillis(getNumParam('network', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['network_type'] = $db->readNetworkType($endTimestamp, $windowDuration);
  }
  // Total traffic. TODO: This should give traffic within the window.
  $windowDuration = minutesToMillis(getNumParam('traffic', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['traffic'] = $db->readTransferVolume();
  }
  // Lag.
  $windowDuration = minutesToMillis(getNumParam('lag', 0, 1, $timeSeriesMaxMinutes));
  if ($windowDuration > 0) {
    $result['lag'] = $db->readLag($endTimestamp, $windowDuration, $maxSamples);
  }
  return $result;
}

try {
  $stats = computeStats();
} catch (Exception $e) {
  http_response_code(500);
}
echo json_encode($stats);
?>