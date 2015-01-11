<?php
require_once 'common.php';

// TODO: Return response (which might be an update) even when DB queries fail. DB queries should
// still be more robust.

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
  $settings = $db->getAppSettings();
  $response = array();
  try {
    $db->beginTransaction();
    $meta = $data['meta'];  // Metadata is required in each request.
    $logger->debug('client md5: '.$meta['md5'].' - server has: '.$settings['md5']);

    // First check whether a client update is available. Note that on startup the client always
    // downloads the latest version, so this applies only to updates while the client is running.
    // When the client is started directly (i.e. not via the wrapper) it sets its md5 to
    // NOT_AVAILABLE. We don't want to exit in that case.
    if (isset($meta['md5']) && $meta['md5'] != NOT_AVAILABLE
        && isset($settings['md5']) && $meta['md5'] != $settings['md5']) {
      $logger->notice('updating client '.$meta['md5'].' to '.$settings['md5']);
      $response['exit'] = 0;  // retval 0 will exit -> update -> restart the client
      // We trigger the client update also if our status is not OK, since that is often caused by an
      // outdated client version. Updating would drop the data that the old client was trying to
      // upload, but since this event should be rare we accept that.
    }

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

    $db->commit();

    $response['status'] = 'ok';
    // TODO: Add support for reboot, shutdown etc.
  } catch (Exception $e) {
    $db->rollback();
    $logger->critical('Exception in rx.php: '.$e);
    $response['status'] = 'failure';
  }
  return $response;
}

$jsonResponse = json_encode(handleRequest());
echo $jsonResponse;
Logger::Instance()->debug('RESPONSE: '.$jsonResponse);
?>
