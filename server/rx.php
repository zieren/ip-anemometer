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

    // Metadata is required in each request.
    $meta = $data[META_KEY];
    // TODO: Create a separate table for connection quality, and store failed uploads, signal
    // quality (eventually) etc. there.
    $meta[FAILED_UPLOADS_KEY] = $data[UPLOAD_KEY][FAILED_UPLOADS_KEY];
    $db->insertMetadata($meta , $_SERVER['REMOTE_ADDR']);

    $settings = $db->getAppSettings();
    $logger->debug('client md5: '.$meta[CLIENT_MD5].' - server has: '.$settings[CLIENT_MD5]);

    $response = array();

    // The client does not set CLIENT_MD5 when it's started directly (and not via the wrapper).
    if (isset($meta[CLIENT_MD5]) && $meta[CLIENT_MD5] != NOT_AVAILABLE
        && isset($settings[CLIENT_MD5]) && $meta[CLIENT_MD5] != $settings[CLIENT_MD5]) {
      $logger->notice('updating client '.$meta[CLIENT_MD5].' to '.$settings[CLIENT_MD5]);
      $response[COMMAND_EXIT] = 0;  // retval 0 will exit -> update -> restart the client
    }

    if (isset($data[WIND_KEY])) {
      $db->insertWind($data[WIND_KEY]);
    }

    if (isset($data[TEMP_KEY])) {
      $db->insertTemperature($data[TEMP_KEY]);
    }

    if (isset($data['link'])) {
      $db->insertLinkStatus($data['link']);
    }

    $response[RESPONSE_STATUS] = RESPONSE_STATUS_OK;
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
