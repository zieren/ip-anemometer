<html>
<body>
<?php
/**
 * Prune data and log files older than N days.
 */

require_once '../common/common.php';

/** Converts a date in the form of YYYY-MM-DD (e.g. 2001-12-31) to a timestamp in millis. */
function dateStringToTimestamp($dateString) {
  $date = strptime($dateString, '%Y-%m-%d');
  return 1000 * mktime(0, 0, 0, $date['tm_mon'] + 1, $date['tm_mday'], $date['tm_year'] + 1900);
}

if (get($_GET['days'], null)) {
  $days = intval($_GET['days']);
  if ($days > 0) {
    // Delete database records.
    $pruneTimestamp = timestamp() - daysToMillis($days);
    $db = new Database(false /* create missing tables */);
    $db->pruneTables($pruneTimestamp);

    // Delete log files. Parse KLogger default file name format to determine date.
    $dateString = date('Y-m-d H:i:s', $pruneTimestamp / 1000);
    $logfiles = scandir('../logs');
    foreach ($logfiles as $f) {
      if (preg_match(LOG_PATTERN, $f, $matches)) {
        $ts = dateStringToTimestamp($matches[1].'-'.$matches[2].'-'.$matches[3])
            + daysToMillis(1);  // We assume 00:00:00 on the file date, but write until 24h later.
        if ($ts < $pruneTimestamp) {
          unlink($f);
        }
      }
    }

    echo '<p>Deleted data and logs prior to '.$dateString.'</p>';
  } else {
    echo '<p><b>Missing "?days=..."</b></p>';
  }
} else {
  echo '<p><b>Missing "?days=..."</b></p>';
}
?>
<p><a href="index.php">Back to console</a></p>
</body>
</html>
