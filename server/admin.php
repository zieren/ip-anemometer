<html>
<body>
<?php
require_once 'common.php';

// TODO: Be sure to give credits to KLogger.

$db = new Database(true /* create missing tables */);

if (isset($_POST["reset"]) && $_POST["confirm"]) {
  $db->dropTables();
  $db->createMissingTables();
} else if (isset($_POST["updateConfig"])) {
  // TODO: This should sanitize the user input.
  $db->updateConfig($_POST["configKey"], $_POST["configValue"]);
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
  <input type="submit" name="'."updateConfig".'" value="Update">
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
