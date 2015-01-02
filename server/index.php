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
  if ($error = $db->createTables()) {
    echo '<p><b>table creation failed: '.$error.'</b></p>';
  } else {
    echo '<p><b>tables created OK</b></p>';
  }
}

// Apply settings.
$settings = $db->getAppSettings();
$logger = new Katzgrau\KLogger\Logger(LOG_DIR, getLogLevel($settings[LOG_LEVEL_KEY]));
if (isset($settings[LOG_LEVEL_KEY])) {
  $db->setLogLevel(getLogLevel($settings[LOG_LEVEL_KEY]));
}

if (isset($_POST[$DROP]) && $_POST[$CONFIRM]) {
  if ($error = $db->dropTables()) {
    echo '<p><b>drop tables failed: '.$error.'</b></p>';
  } else {
    echo '<p><b>tables dropped OK</b></p>';
  }
} else if (isset($_POST[$UPDATE_SETTING])) {
  // TODO: This should sanitize the user input.
  $db->updateSetting($_POST[$SETTING_KEY], $_POST[$SETTING_VALUE]);
} else if (isset($_POST[$CLEAR_SETTING])) {
  // TODO: This should sanitize the user input.
  $db->clearSetting($_POST[$SETTING_KEY]);
}

// Compute actual wind measurements.

function printRecentWindStats($minutes, $endTimestamp = 0) {
  global $db;
  if (!$endTimestamp) {
    $endTimestamp = timestamp();
  }
  $windStats = $db->computeWindStats($endTimestamp, $minutes * 60 * 1000, 30);
  if (is_null($windStats)) {
    echo '<p>n/a</p>';
    return;
  }
  $latency = $endTimestamp - $windStats[WIND_KEY_END_TS];
  $latencyText = '@latency='.formatDuration($latency);
  if ($latency > 15 * 60 * 1000) {  // TODO: Extract this.
    $latencyText = '<b><font color="red">'.$latencyText.'</font></b>';
  }
  echo 'Last '.$minutes.' minutes [km/h] '.$latencyText.'<br />';
  echo '<table border="1" cellpadding="3">
    <tr><td>avg</td><td>max</td><td>time of max</td></tr>
    <tr><td>'.round($windStats[WIND_KEY_AVG], 1)
        .'</td><td>'.round($windStats[WIND_KEY_MAX], 1)
        .'</td><td>'.formatTimestamp($windStats[WIND_KEY_MAX_TS])
        .'</td></tr>
  </table>';
  printHistogram($windStats[WIND_KEY_HIST]);
}

function printHistogram($histogram) {
  $kmhRow = '<td>km/h</td>';
  $percentRow = '<td>%</td>';
  $percentUpRow = '<td>%&gt;=</td>';
  $percentUp = 1;
  foreach ($histogram as $k => $v) {
    $kmhRow .= '<td align="right">'.$k.'</td>';
    $percentRow .= '<td align="right">'.round($v * 100, 1).'</td>';
    $percentUpRow .= '<td align="right">'.round($percentUp * 100, 1).'</td>';
    $percentUp -= $v;
  }
  echo '<table border="1" cellpadding="3">
    <tr>'.$kmhRow.'</tr>
    <tr>'.$percentRow.'</tr>
    <tr>'.$percentUpRow.'</tr>
    </table>';
}

echo '<h2>Wind</h2>';

printRecentWindStats(60);
printRecentWindStats(30);
printRecentWindStats(5);

echo
'<hr />
<h2>Temperature</h2>';
$db->echoTemp();

echo '
<hr /><h2>Settings</h2>';
$db->echoSettings();

if ($_GET['admin'] === null) {
  return;
}

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
