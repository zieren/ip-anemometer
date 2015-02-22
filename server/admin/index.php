<html>
<body>
<?php
require_once '../common/common.php';

$db = new Database(true /* create missing tables */);
if (!isset($db->getConfig()['s:client_md5'])) {  // first run
  $db->populateConfig(CLIENT_APP_CFG_DEFAULT_FILENAME);
  buildClientAppZip($db);
}

if (isset($_POST["clearAll"]) && $_POST["confirm"]) {
  $db->dropTablesExceptConfig();
  $db->createMissingTables();
} else if (isset($_POST["configDefaults"]) && $_POST["confirm"]) {
  $db->populateConfig(CLIENT_APP_CFG_DEFAULT_FILENAME);
  buildClientAppZip($db);
} else if (isset($_POST["setConfig"]) || isset($_POST["clearConfig"])) {
  // TODO: This should sanitize the user input.
  $key = ($_POST["serverKey"] ? "s:" : "c:").$_POST["configKey"];
  if (isset($_POST["setConfig"])) {
    $db->setConfig($key, $_POST["configValue"]);
  } else {
    $db->clearConfig($key);
  }
  if (!$_POST["serverKey"]) {
    buildClientAppZip($db);
  }
}

echo '
<h1>'.IPA_GREETING.'</h1>
<p>(c) 2014-2015 J&ouml;rg Zieren (<a href="http://zieren.de">zieren.de</a>), GNU GPL v3.
Components: <a href="http://codefury.net/projects/klogger/">KLogger</a> (c) 2008-2014 Kenny
Katzgrau, MIT license</p>
<p><a href="../view/">View anemometer</a></p>

<h2>Configuration</h2>';
$db->echoConfig();

echo '
<form action="" method="post" enctype="multipart/form-data">
  <input type="checkbox" name="serverKey" />server
  <input type="text" name="'."configKey".'" value="key">
  <input type="text" name="'."configValue".'" value="value">
  <input type="submit" name="'."setConfig".'" value="Set">
  <input type="submit" name="'."clearConfig".'" value="Clear">
</form>';

echo
'<hr />
<h2>Upload Client App</h2>
    <form action="upload_file.php" method="post" enctype="multipart/form-data">
  <label for="file">Update:</label>
  <input type="file" name="file" id="file">
  <input type="submit" name="submit" value="Upload">
</form>';

echo
'<hr />
<h2>Manage Database</h2>
<form action="" method="post">
  <input type="submit" name="clearAll" value="CLEAR ALL DATA" />
  <input type="submit" name="configDefaults" value="config defaults" />
  <input type="checkbox" name="confirm" />Yes, I really really want to!
</form>
<hr />';
?>
</body>
</html>
