<html>
<body>
<?php
/**
 * Store an update file (a .zip) on the server.
 */

require_once '../common/common.php';

$allowedExts = array('zip');
$allowedTypes = array('application/x-zip-compressed', 'application/zip');
$extension = strtolower(array_slice(explode('.', $_FILES['file']['name']), -1, 1)[0]);

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
  // Move file first, since this is more likely to fail (permissions) than the DB update.
  move_uploaded_file($_FILES['file']['tmp_name'], CLIENT_APP_ZIP_FILENAME);
  buildClientAppZip(new Database());
  echo 'Added client config.<br>';
  echo 'Done.<br>';
}
echo '</p>';
?>
<p><a href="index.php">Back to console</a></p>
</body>
</html>
