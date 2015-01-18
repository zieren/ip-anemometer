<html>
<body>
<?php
require_once 'common.php';

// TODO: Be sure to give credits to KLogger.

function lazilySetClientAppMd5($db) {
  $config = $db->getConfig();
  if (!isset($config['s:client_app_md5'])) {
    $md5 = md5_file(CLIENT_UPDATE_FILENAME);
    if ($md5) {
      $db->setConfig("s:client_app_md5", $md5);
    }
  }
}

$db = new Database(true /* create missing tables */);
lazilySetClientAppMd5($db);

if (isset($_POST["reset"]) && $_POST["confirm"]) {
  $db->dropTables();
  $db->createMissingTables();
  lazilySetClientAppMd5($db);
} else if (isset($_POST["setConfig"])) {
  // TODO: This should sanitize the user input.
  $db->setConfig($_POST["configKey"], $_POST["configValue"]);
} else if (isset($_POST["clearConfig"])) {
  // TODO: This should sanitize the user input.
  $db->clearConfig($_POST["configKey"]);
}

echo '
<h1>IPA Anemometer</h1>

<h2>Configuration</h2>';
$db->echoConfig();

echo '
<hr />
<h2>Update Configuration</h2>
<form action="" method="post" enctype="multipart/form-data">
  <input type="text" name="'."configKey".'" value="key">
  <input type="text" name="'."configValue".'" value="value">
  <input type="submit" name="'."setConfig".'" value="Set">
  <input type="submit" name="'."clearConfig".'" value="Clear">
</form>';

echo
'<hr />
<h2>Upload Client Update</h2>
    <form action="upload_file.php" method="post" enctype="multipart/form-data">
  <label for="file">Update:</label>
  <input type="file" name="file" id="file">
  <input type="submit" name="submit" value="Upload">
</form>';

echo
'<hr />
<h2>Manage Database</h2>
<form action="" method="post">
  <input type="submit" name="reset" value="RESET EVERYTHING" />
  <input type="checkbox" name="confirm" />Yes, I really really want to!
</form>
<hr />';
?>
</body>
</html>
