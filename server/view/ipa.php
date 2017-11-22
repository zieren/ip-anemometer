<?php
require_once '../common/common.php';

header("Access-Control-Allow-Origin: *");

/*
 * Request arguments:
 * - upTo: An optional end timestamp, default is now.
 * - wind: If present, the start timestamp of the requested wind data. If absent, no wind data is
 *         returned.
 * - temp_hum, adc, door, pilots, cpu_temp, signal, network, traffic, lag: Like wind.
 * - samples: Number of samples per chart, i.e. data resolution. Should be chosen to suit the chart
 *            width in pixels.
 * - dummy: Set to 1 to return dummy data.
 */

/** Returns a numeric request parameter, limiting its range and using a default if it's absent. */
function getNumParam($name, $default, $min, $max) {
  if (isset($_REQUEST[$name]) && is_numeric($_REQUEST[$name])) {
    return max($min, min($_REQUEST[$name], $max));
  }
  return $default;
}

/**
 * Limits the start timestamp specified in the request so that given the specified end timestamp,
 * the maximum duration is not exceeded.
 */
function getEffectiveStartTimestamp($requestParameter, $endTimestamp, $maxMinutes) {
  if (isset($_REQUEST[$requestParameter]) && is_numeric($_REQUEST[$requestParameter])) {
    return max($_REQUEST[$requestParameter], $endTimestamp - minutesToMillis($maxMinutes));
  }
  return null;
}

function computeStats() {
  $db = new Database();
  $config = $db->getConfig();
  // Limit the amount of data to process.
  $timeSeriesMaxMinutes = $config['s:query_time_series_max_minutes'];
  $eventMaxMinutes = $config['s:query_event_max_minutes'];

  // Timestamps: Start is the oldest timestamp to consider, end is the newest, by default now.
  $endTimestamp = timestamp();
  // The end timestamp defaults to now, but can be specified in the request.
  if (isset($_REQUEST['upTo']) && is_numeric($_REQUEST['upTo'])) {
    $endTimestamp = $_REQUEST['upTo'];
  }

  // Limit the number of samples to keep the charts sane.
  $maxSamples = getNumParam('samples', REQ_TIME_SERIES_POINTS_DEFAULT,
      REQ_TIME_SERIES_POINTS_MIN, REQ_TIME_SERIES_POINTS_MAX);

  // Status is always returned.
  $result = array('status' => $db->readStatus());

  // Wind
  $startTimestamp = getEffectiveStartTimestamp('wind', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['wind'] = $db->computeWindStats($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Temperature and humidity
  $startTimestamp = getEffectiveStartTimestamp('temp_hum', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['temp_hum'] = $db->readTempHum($startTimestamp, $endTimestamp, $maxSamples);
  }
  // ADC. TODO: Handle ADC channels individually?
  $startTimestamp = getEffectiveStartTimestamp('adc', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['adc'] = $db->readAdcValues($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Door
  $startTimestamp = getEffectiveStartTimestamp('door', $endTimestamp, $eventMaxMinutes);
  if ($startTimestamp != null) {
    $result['door'] = $db->readDoor($startTimestamp, $endTimestamp);
  }
  // Pilots
  $startTimestamp = getEffectiveStartTimestamp('pilots', $endTimestamp, $eventMaxMinutes);
  if ($startTimestamp != null) {
    $result['pilots'] = $db->readPilots($startTimestamp, $endTimestamp);
  }
  // CPU temperature
  $startTimestamp = getEffectiveStartTimestamp('cpu_temp', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['cpu_temp'] = $db->readCpuTemp($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Signal strength
  $startTimestamp = getEffectiveStartTimestamp('signal', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['signal_strength'] = $db->readSignalStrength($startTimestamp, $endTimestamp, $maxSamples);
  }
  // Network type
  $startTimestamp = getEffectiveStartTimestamp('network', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['network_type'] = $db->readNetworkType($startTimestamp, $endTimestamp);
  }
  // Traffic
  $startTimestamp = getEffectiveStartTimestamp('traffic', $endTimestamp, $timeSeriesMaxMinutes);
  if ($startTimestamp != null) {
    $result['traffic'] = $db->readTraffic($startTimestamp, $endTimestamp);
  }
  // Lag
  $startTimestamp = getEffectiveStartTimestamp('lag', $endTimestamp, $timeSeriesMaxMinutes);
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