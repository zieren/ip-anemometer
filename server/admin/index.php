<html>
<body>
<?php
require_once '../common/common.php';

$db = new Database(true /* create missing tables */);
if (!isset($db->getConfig()['s:client_md5'])) {  // first run
  echo '<h2>Initializing...</h2><p><i>This seems to be the first run. Setting default config...';
  $db->populateConfig();
  buildClientAppZip($db);
  echo ' done.</i></p>';
}

if (isset($_POST['clearAll']) && $_POST['confirm']) {
  $db->dropTablesExceptConfig();
  $db->createMissingTables();
} else if (isset($_POST['configDefaults']) && $_POST['confirm']) {
  $db->populateConfig();
  buildClientAppZip($db);
} else if (isset($_POST['setConfig']) || isset($_POST['clearConfig'])) {
  // TODO: This should sanitize the user input.
  $key = ($_POST['configComponent'] == 'server' ? 's:' : 'c:').trim($_POST['configKey']);
  if (isset($_POST['setConfig'])) {
    $db->setConfig($key, $_POST['configValue']);
  } else {
    $db->clearConfig($key);
  }
  if ($_POST['configComponent'] == 'client') {
    buildClientAppZip($db);
  }
}

echo '
<h1>'.IPA_SERVER_HEADING.'</h1>
<p>(c) 2016 J&ouml;rg Zieren - <a href="http://zieren.de">zieren.de</a> - GNU GPL v3.
Components:
<a href="http://codefury.net/projects/klogger/">KLogger</a> by Kenny Katzgrau, MIT license.
<a href="http://spin.js.org/">spin.js</a> by Felix Gnass, Timothy Gu and others, MIT license.
</p>
<p><a href="../view/">View anemometer</a></p>

<h2>Configuration</h2>';
$db->echoConfig();

echo '
<form action="" method="post" enctype="multipart/form-data">
  <select name="configComponent">
    <option value="client">Client</option>
    <option value="server">Server</option>
  </select>
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
  <input type="submit" name="configDefaults" value="set missing config values to defaults" />
  <input type="checkbox" name="confirm" />Yes, I really really want to!
</form>';
?>
</body>
</html>
