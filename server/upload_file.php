<html>
<body>
<?php
/* Store an update file (a .zip) on the server.
 */

require_once 'common.php';

$allowedExts = array('zip');
$allowedTypes = array('application/x-zip-compressed', 'application/zip');
$extension = strtolower(end(explode('.', $_FILES['file']['name'])));

echo '<p>';
if (!in_array($_FILES['file']['type'], $allowedTypes)) {
  echo 'Invalid file type: '.$_FILES['file']['type'];
} elseif ($_FILES['file']['size'] > CLIENT_APP_MAX_SIZE) {
  echo 'File too large: '.$_FILES['file']['size'];
} elseif (!in_array($extension, $allowedExts)) {
  echo 'Invalid extension: '.$extension;
} elseif ($_FILES['file']['error'] != 0) {
  echo 'Upload failed. Error code: '.$_FILES['file']['error'];
} else {
  echo 'File: '.$_FILES['file']['name'].'<br>';
  echo 'Type: '.$_FILES['file']['type'].'<br>';
  echo 'Size: '.$_FILES['file']['size'].'<br>';
  $tmpName = $_FILES['file']['tmp_name'];
  $md5 = md5_file($tmpName);
  echo 'MD5: '.$md5.'<br>';
  $db = new Database();
  buildClientAppZip($tmpName, $db);
  echo 'Added client config.<br>';
  move_uploaded_file($tmpName, CLIENT_APP_ZIP_FILENAME);
  $db->setConfig('s:client_app_md5', $md5);
  echo 'Done.<br>';
}
echo '</p>';
?>
<p><a href="admin.php">Back to console</a></p>
</body>
</html>
