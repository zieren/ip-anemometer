<?php
require_once 'common.php';

// TODO: Return response (which might be an update) even when DB queries fail. DB queries should
// still be more robust.
// TODO: Extract magic literals.

function handleRequest($logger) {
  $db = new Database();

  $response = array();
  $contentBz2 = file_get_contents('php://input');
  if (strlen($contentBz2) > 0) {  // note that bzip2 never outputs ""
    $dataJson = bzdecompress($contentBz2);
    if (!is_string($dataJson)) {
      $logger->alert('bzip2 decompression failed: '.$dataJson);
      $response['status'] = 'bzip2 decompression failed';
      return $response;
    }
    $data = json_decode($dataJson, true);
    if ($data === NULL) {
      $logger->alert('json decoding failed');  // don't log potentially large $dataJson
      $response['status'] = 'json decoding failed';
      return $response;
    }

    $db->beginTransaction();

    // Metadata is required in each request.
    $meta = $data['meta'];

    $meta['upto'] = 0;  // means n/a
    if (isset($data['wind'])) {
      $meta['upto'] = $db->insertWind($data['wind']);
    }

    $meta['fails'] = $data['upload']['fails'];
    // TODO: Throw exceptions and rollback on failure. However, this doesn't prevent: "ok" reply to
    // client is lost, client resends all data, all rows are overwritten except in hist table.
    $db->insertMetadata($meta);

    $settings = $db->getAppSettings();
    $logger->debug('client md5: '.$meta['md5'].' - server has: '.$settings['md5']);

    $response = array();

    // The client does not set the md5 when it's started directly (i.e. not via the wrapper). We
    // don't want to exit in that case.
    if (isset($meta['md5']) && $meta['md5'] != NOT_AVAILABLE
        && isset($settings['md5']) && $meta['md5'] != $settings['md5']) {
      $logger->notice('updating client '.$meta['md5'].' to '.$settings['md5']);
      $response['exit'] = 0;  // retval 0 will exit -> update -> restart the client
    }

    if (isset($data['temp'])) {
      $db->insertTemperature($data['temp']);
    }

    if (isset($data['link'])) {
      $db->insertLinkStatus($data['link']);
    }

    $db->commit();

    $response['status'] = 'ok';
    // TODO: Add support for reboot, shutdown etc.

    return $response;
  }
}

$logger = Logger::Instance();
$response = handleRequest($logger);
$jsonResponse = json_encode($response);
echo $jsonResponse;
$logger->debug('RESPONSE: '.$jsonResponse);

?>
