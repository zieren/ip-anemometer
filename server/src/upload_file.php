<?php
/* Store an update file (a .zip) on the server.
 */

require_once 'common.php';

$allowedExts = array('zip');
$allowedTypes = array('application/x-zip-compressed', 'application/zip');
$temp = explode('.', $_FILES['file']['name']);
$extension = end($temp);

echo '<p>';
if (!in_array($_FILES['file']['type'], $allowedTypes)) {
  echo 'Invalid file type: '.$_FILES['file']['type'];
} elseif ($_FILES['file']['size'] > CLIENT_UPDATE_MAX_SIZE) {
  echo 'File too large: '.$_FILES['file']['size'];
} elseif (!in_array($extension, $allowedExts)) {
  echo 'Invalid extension: '.$extension;
} elseif ($_FILES['file']['error'] != 0) {
  echo 'Upload failed. Error code: '.$_FILES['file']['error'];
} else {
  echo 'File: '.$_FILES['file']['name'].'<br>';
  echo 'Type: '.$_FILES['file']['type'].'<br>';
  echo 'Size: '.$_FILES['file']['size'].'<br>';
  move_uploaded_file($_FILES['file']['tmp_name'], CLIENT_UPDATE_FILENAME);
  echo 'Done.<br>';
}
echo '</p>';
?>
<p><a href="index.php?admin">Back to console</a></p>
