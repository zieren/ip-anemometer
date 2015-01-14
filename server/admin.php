<html>
<body>
<?php
require_once 'common.php';

// TODO: Be sure to give credits to KLogger.

$CREATE = "create";
$DROP = "drop";
$CONFIRM = "confirm";
$SETTING_KEY = "settingKey";
$SETTING_VALUE = "settingValue";
$UPDATE_SETTING = "updateSetting";
$CLEAR_SETTING = "clearSetting";

$db = new Database();

// Create tables first, otherwise subsequent operation will fail.
if (isset($_POST[$CREATE]) && $_POST[$CONFIRM]) {
  $db->createTables();
} else if (isset($_POST[$DROP]) && $_POST[$CONFIRM]) {
  $db->dropTables();
} else if (isset($_POST[$UPDATE_SETTING])) {
  // TODO: This should sanitize the user input.
  $db->updateSetting($_POST[$SETTING_KEY], $_POST[$SETTING_VALUE]);
} else if (isset($_POST[$CLEAR_SETTING])) {
  // TODO: This should sanitize the user input.
  $db->clearSetting($_POST[$SETTING_KEY]);
}

echo '
<h1>IPA Anemometer</h1>

<h2>Settings</h2>';
$db->echoSettings();

echo '
<hr />
<h2>Update Setting</h2>
<form action="" method="post" enctype="multipart/form-data">
  <input type="text" name="'.$SETTING_KEY.'" value="key">
  <input type="text" name="'.$SETTING_VALUE.'" value="value">
  <input type="submit" name="'.$UPDATE_SETTING.'" value="Update">
  <input type="submit" name="'.$CLEAR_SETTING.'" value="Clear">
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
  <input type="submit" name="' . $CREATE . '" value="CREATE TABLES" />
  <input type="submit" name="' . $DROP . '" value="DROP TABLES" />
  <input type="checkbox" name="' . $CONFIRM . '" />Yes, I really really want to!
</form>
<hr />';
?>
</body>
</html>
