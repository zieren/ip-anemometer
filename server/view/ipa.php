<?php
require_once '../common/common.php';

header("Access-Control-Allow-Origin: *");

function getNumParam($name, $default, $min, $max) {
  if (isset($_REQUEST[$name]) && is_numeric($_REQUEST[$name])) {
    return max($min, min($_REQUEST[$name], $max));
  }
  return $default;
}

function getEffectiveStartTimestamp($requestParameter, $timestampNow, $maxMinutes) {
  if (isset($_REQUEST[$requestParameter]) && is_numeric($_REQUEST[$requestParameter])) {
    return max($_REQUEST[$requestParameter], $timestampNow - minutesToMillis($maxMinutes));
  }
  return null;
}

function computeStats() {
  $db = new Database();
  $config = $db->getConfig();
  $timeSeriesMaxMinutes = $config['s:query_time_series_max_minutes'];
  $eventMaxMinutes = $config['s:query_event_max_minutes'];

  $timestampNow = timestamp();
  $endTimestamp = $timestampNow;
  if (isset($_REQUEST['upTo']) && is_numeric($_REQUEST['upTo'])) {
    $endTimestamp = $_REQUEST['upTo'];
  }
  $maxSamples = getNumParam('samples', REQ_TIME_SERIES_POINTS_DEFAULT,
      REQ_TIME_SERIES_POINTS_MIN, REQ_TIME_SERIES_POINTS_MAX);

  // Status is always returned.
  $result = array('status' => $db->readStatus());

  // Wind
  $startTimestamp = getEffectiveStartTimestamp('wind', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['wind'] = $db->computeWindStats($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Temperature and humidity
  $startTimestamp = getEffectiveStartTimestamp('tempHum', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['temp_hum'] = $db->readTempHum($startTimestamp, $endTimestamp, $maxSamples);
  }
  // ADC. TODO: Handle ADC channels individually?
  $startTimestamp = getEffectiveStartTimestamp('adc', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['adc'] = $db->readAdcValues($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Door
  $startTimestamp = getEffectiveStartTimestamp('door', $timestampNow, $eventMaxMinutes);
  if ($startTimestamp != null) {
    $result['door'] = $db->readDoor($startTimestamp, $endTimestamp);
  }
  // Pilots
  $startTimestamp = getEffectiveStartTimestamp('pilots', $timestampNow, $eventMaxMinutes);
  if ($startTimestamp != null) {
    $result['pilots'] = $db->readPilots($startTimestamp, $endTimestamp);
  }
  // CPU temperature
  $startTimestamp = getEffectiveStartTimestamp('cpuTemp', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['cpu_temp'] = $db->readTemperature($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Signal strength
  $startTimestamp = getEffectiveStartTimestamp('signal', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['signal_strength'] = $db->readSignalStrength($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Network type
  $startTimestamp = getEffectiveStartTimestamp('network', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['network_type'] = $db->readNetworkType($startTimestamp, $endTimestamp);
  }
  // Traffic
  $startTimestamp = getEffectiveStartTimestamp('traffic', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['traffic'] = $db->readTransferVolume($startTimestamp, $endTimestamp);
  }
  // Lag
  $startTimestamp = getEffectiveStartTimestamp('lag', $timestampNow, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['lag'] = $db->readLag($startTimestamp, $endTimestamp, $maxSamples);
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