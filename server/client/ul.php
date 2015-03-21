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

  $db = new Database();  // TODO: Handle failure.
  $config = $db->getConfig();
  $response = array();
  try {
    $db->beginTransaction();
    $meta = $data['meta'];  // Metadata is required in each request.
    $clientAppMd5 = get($meta['md5'], NOT_AVAILABLE);
    $serverAppMd5 = get($config['s:client_md5'], NOT_AVAILABLE);
    $logger->debug('client md5: '.$clientAppMd5.' -- server has: '.$serverAppMd5);

    // First check whether a client update is available. Note that on startup the client always
    // downloads the latest version, so this applies only to updates while the client is running.
    // When the client is started directly (i.e. not via the wrapper) it sets its md5 to
    // NOT_AVAILABLE. We don't want to exit in that special case.
    if ($clientAppMd5 != $serverAppMd5
        && $clientAppMd5 != NOT_AVAILABLE && $serverAppMd5 != NOT_AVAILABLE) {
      $logger->notice('updating client '.$clientAppMd5.' to '.$serverAppMd5);
      $response['exit'] = 0;  // retval 0 will exit -> update -> restart the client
      // We trigger the client update also if our status is not OK, since that is often caused by an
      // outdated client version. Updating drops the data that the old client was trying to upload,
      // but since this event should be rare we accept that.
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

    if (isset($data['door'])) {
      $db->insertDoor($data['door']);
    }

    $db->commit();

    $response['status'] = 'ok';
    // TODO: Add support for reboot, shutdown etc.
  } catch (Exception $e) {
    $db->rollback();
    $logger->critical('Exception in ul.php: '.$e);
    $response['status'] = 'failure';
  }
  return $response;
}

$jsonResponse = json_encode(handleRequest());
echo $jsonResponse;
Logger::Instance()->debug('RESPONSE: '.$jsonResponse);
?>
