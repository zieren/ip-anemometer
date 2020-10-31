<html>
<body>
<script type="text/javascript">
function enableDestructiveButtons(toggleCheckbox) {
  var checkbox = document.getElementById('idIpaVwConfirm');
  if (toggleCheckbox) {
    checkbox.checked = !checkbox.checked;
  }
  var buttons = document.getElementsByClassName('ipaDestructive');
  for (var i = 0; i < buttons.length; i++) {
    buttons[i].disabled = !checkbox.checked;
  }
}
</script>
<?php
require_once '../common/common.php';

$db = new Database(true /* create missing tables */);
if (!array_key_exists('c:client_version', $db->getConfig())) {  // first run
  echo '<h2>Initializing...</h2><p><i>This seems to be the first run. Setting default config...';
  $db->populateConfig();
  buildClientAppZip($db);
  echo ' done.</i></p>';
}

if (isset($_POST['clearAll'])) {
  $db->dropTablesExceptConfig();
  $db->createMissingTables();
} else if (isset($_POST['configDefaults'])) {
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
<p>(c) 2019 J&ouml;rg Zieren - <a href="http://zieren.de">zieren.de</a> - GNU GPL v3.
Components:
<a href="http://codefury.net/projects/klogger/">KLogger</a> by Kenny Katzgrau, MIT license.
<a href="http://spin.js.org/">spin.js</a> by Felix Gnass, Timothy Gu and others, MIT license.
<a href="https://github.com/flatpickr/flatpickr">flatpickr</a>, MIT license.
</p>
<p><a href="../view/">View anemometer</a></p>

<h2>Configuration</h2>';
$db->echoConfig();

echo '
<form method="post" enctype="multipart/form-data">
  <select name="configComponent">
    <option value="client">Client</option>
    <option value="server">Server</option>
  </select>
  <input type="text" name="configKey" value="" placeholder="key">
  <input type="text" name="configValue" value="" placeholder="value">
  <input type="submit" name="setConfig" value="Set">
  <input type="submit" name="clearConfig" value="Clear">
  <p><input type="submit" name="configDefaults" value="set defaults for missing keys" /></p>
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
<h2>Manage Database/Logs</h2>
<form action="prune.php" method="get">
  <p>
    PRUNE data and logs older than <input type="text" name="days" value=""> days.
    <input class="ipaDestructive" type="submit" value="PRUNE" disabled />
  </p>
</form>
<form method="post">
  <p>
    CLEAR ALL DATA except config
    <input class="ipaDestructive" type="submit" name="clearAll" value="CLEAR" disabled />
  </p>
</form>
<form>
  <input type="checkbox" name="confirm" id="idIpaVwConfirm"
      onclick="enableDestructiveButtons(false)"/>
  <span onclick="enableDestructiveButtons(true)">Yes, I really really want to!</span>
</form>
';
?>
</body>
</html>
