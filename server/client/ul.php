<?php
require_once '../common/common.php';

function handleRequest() {
  $logger = Logger::Instance();
  $contentBz2 = file_get_contents('php://input');
  if (!$contentBz2) {  // note that bzip2 never outputs ""
    return array('status' => 'no data received');
  }
  $data = json_decode(bzdecompress($contentBz2), true);
  if (!$data) {
    $logger->critical('json decoding or bzip2 decompression failed');
    return array('status' => 'invalid json or bzip2');
  }
  $logger->debug('Received data with keys: '.implode(array_keys($data), ', '));

  $db = new Database();  // TODO: Handle failure.
  $config = $db->getConfig();
  $response = array();
  try {
    $db->beginTransaction();
    $meta = $data['meta'];  // Metadata is required in each request.
    $clientVersionOnClient = get($meta['client_version'], NOT_AVAILABLE);
    $clientVersionOnServer = get($config['c:client_version'], NOT_AVAILABLE);
    $logger->debug('client version: '.$clientVersionOnClient
        .' -- server has: '.$clientVersionOnServer);

    // Check whether the server has a different client version, and if so, make the wrapper download
    // it. We don't care whether the version on the server is actually newer or older.
    if ($clientVersionOnClient != $clientVersionOnServer) {
      $logger->notice('updating client '.$clientVersionOnClient.' to '.$clientVersionOnServer);
      $response['exit'] = 102;  // retval 102 will exit -> update -> restart the client
      // We trigger the client update also if our status is not OK, since that may be caused by an
      // outdated client version.
    }

    // TODO: If 'upto' is only the newest wind timestamp, we don't really need it.
    $meta['upto'] = 0;  // means n/a
    if (isset($data['wind'])) {
      $meta['upto'] = $db->insertWind($data['wind']);
    }
    $meta['fails'] = $data['upload']['fails'];
    $db->insertMetadata($meta);

    if (isset($data['temp'])) {
      $db->insertTemperature($data['temp']);
    }

    if (isset($data['link'])) {
      $db->insertLinkStatus($data['link']);
    }

    if (isset($data['temp_hum'])) {
      $db->insertTempHum($data['temp_hum']);
    }

    if (isset($data['door'])) {
      $db->insertDoor($data['door']);
    }

    if (isset($data['pilots'])) {
      $db->insertPilotCount($data['pilots']);
    }

    if (isset($data['adc'])) {
      $db->insertAdcValues($data['adc']);
    }

    if (isset($data['status'])) {
      $db->insertStatus($data['status']);
    }

    $db->commit();

    $response['status'] = 'ok';
    // TODO: Add support for reboot, shutdown etc.
  } catch (Exception $e) {
    $db->rollback();
    $logger->critical('Exception in ul.php: '.$e);
    $response['status'] = 'failure: '.$e;
  }
  return $response;
}

$jsonResponse = json_encode(handleRequest());
echo $jsonResponse;
Logger::Instance()->debug('RESPONSE: '.$jsonResponse);
?>
