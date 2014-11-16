<?php
require_once 'common.php';
require_once 'config.php';

// TODO: Extract magic literals.

/**
 * General notes:
 * This uses REPLACE instead of INSERT because the client may resend the same timestamp (PK) when
 * the server's "OK" response gets lost (e.g. the client loses the uplink just before receiving the
 * server's response).
 */
class Database {
  /** Our KLogger instance. */
  private $log = null;

  /** Connection to the database. */
  private $mysqli = null;

  /** All tables. */
  private $allTables = null;

  /** Connects to the database, or exits on error. */
  public function __construct() {
    $this->log = new Katzgrau\KLogger\Logger(LOG_DIR);
    $this->allTables = array('coverage', 'wind', 'temp', 'meta', 'settings');
    $this->mysqli = new mysqli('localhost', DB_USER, DB_PASS, DB_NAME);
    if ($this->mysqli->connect_errno) {
      $this->logError('failed to connect to MySQL: (' . $mysqli->connect_errno . ') '
          . $this->mysqli->connect_error);
      exit();
    }
    assert($this->mysqli->select_db(DB_NAME));
  }

  /** Set log level for internal KLogger. */
  public function setLogLevel($level) {
    $this->log->setLogLevelThreshold($level);
  }

  /** Log to file and stdout. */
  private function logError($message) {
    $this->log->error($message);
    echo '<p>ERROR: ' . $message . '</p>';
  }

  /** Log to file and stdout. */
  private function logCritical($message) {
    $this->log->critical($message);
    echo '<p>CRITICAL: ' . $message . '</p>';
  }

  /** Run the specified query. */
  private function query($query) {
    return $this->mysqli->query($query);
  }

  /** @return The connection's 'error' field. */
  private function getError() {
    return $this->mysqli->error;
  }

  /**
   * Create all tables, if not exists.
   *
   * @return null on success, error text on failure.
   */
  public function createTables() {
    if ($this->query(
            'CREATE TABLE IF NOT EXISTS temp (ts BIGINT PRIMARY KEY, t FLOAT NOT NULL)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS wind (ts BIGINT PRIMARY KEY)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS coverage (startup TIMESTAMP PRIMARY KEY, upto TIMESTAMP)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS meta (ts TIMESTAMP PRIMARY KEY, cts TIMESTAMP, '.
            'stratum INT, fails INT, ip VARCHAR(15))')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS settings (k VARCHAR(256) PRIMARY KEY, v TEXT)')) {
      $this->log->notice('tables created');
    } else {
      $this->logError('failed to create tables: ' . $this->getError());
      return $this->getError();
    }
    return null;
  }

  /**
   * Drop all tables, if exists.
   *
   * @return null on success, error text on failure.
   */
  public function dropTables() {
    if ($this->query('DROP TABLE IF EXISTS ' . join(',', $this->allTables))) {
      $this->log->notice('tables dropped');
    } else {
      $this->logError('Failed to drop tables: ' . $this->getError());
      return $this->getError();
    }
    return null;
  }

  public function insertTemperature($temp) {
      if (count($temp) == 0) {
      $this->log->warning('received empty temperature measurements');
      return;
    }
    $q = '';
    foreach ($temp as $v) {
      if ($q != '') {
        $q .= ',';
      }
      $q .= '('.$v[0].','.$v[1].')';
    }

    $q = 'REPLACE INTO temp (ts, t) VALUES '.$q;
    $this->log->debug('QUERY: '.$q);
    if (!$this->query($q)) {
      $this->logCritical('failed to insert temperature measurements: '.$this->getError());
    }
  }

  public function insertWind($wind) {
    if (count($wind) == 0) {
      $this->log->warning('received empty wind measurements');
      return;
    }
    $q = '';
    foreach ($wind as $data_dict) {
      $values = $data_dict[WIND_REVOLUTIONS_KEY];
      // This loop will run more than once iff there were failed uploads, since that results in
      // multiple entries in $wind.
      foreach ($values as $v) {
        if ($q != '') {
          $q .= ',';
        }
        $q .= '('.$v.')';
      }
    }

    // No sensor values available - this is expected in precision mode when there is no wind.
    if ($q != '') {
      $q = 'REPLACE INTO wind (ts) VALUES '.$q;
      $this->log->debug('QUERY: '.$q);
      if (!$this->query($q)) {
        $this->logCritical('failed to insert wind measurements: '.$this->getError());
      }
    }

    // We're only interested in the most recent timestamps and ignore those of previous failed
    // upload attempts (if any).
    $latest_record = $wind[count($wind) - 1];
    $startup_timestamp_seconds = $latest_record[WIND_STARTUP_TIME_KEY];
    $up_to_timestamp_seconds = $latest_record[WIND_UP_TO_TIME_KEY];
    $q = 'REPLACE INTO coverage (startup, upto) VALUES ("'
        .formatTimestamp($startup_timestamp_seconds).'","'
        .formatTimestamp($up_to_timestamp_seconds).'")';
    if (!$this->query($q)) {
      $this->logCritical('failed to insert coverage timestamps: '.$this->getError());
    }
  }

  public function insertMetadata($meta, $ip) {
    $q = 'REPLACE INTO meta (cts, stratum, fails, ip) VALUES ("'.formatTimestamp(
        $meta[CLIENT_TIMESTAMP_KEY]).'",'.$meta[STRATUM_KEY].','.$meta[FAILED_UPLOADS_KEY].',"'
        .$ip.'")';
    $this->log->debug('QUERY: '.$q);
    if (!$this->query($q)) {
      $this->logCritical('failed to insert metadata: ' . $this->getError());
    }
  }

  /** Updates the specified setting. */
  public function updateSetting($key, $value) {
    $q = 'REPLACE INTO settings (k, v) VALUES ("'.$key.'", "'.$value.'")';
    if (!$this->query($q)) {
      $this->logCritical('failed to update setting ('.$key.'='.$value.'): '.$this->getError());
    }
  }

  /** Deletes the specified setting. */
  public function clearSetting($key) {
    $q = 'DELETE FROM settings WHERE k="'.$key.'"';
    if (!$this->query($q)) {
      $this->logCritical('failed to clear setting ('.$key.'): '.$this->getError());
    }
  }

  /** Returns application settings. Lazily initialized. */
  public function getAppSettings() {
    if (!isset($this->appSettings)) {
      $this->appSettings = array();
      if ($result = $this->query('SELECT * FROM settings')) {
        while ($row = $result->fetch_assoc()) {
          $this->appSettings[$row['k']] = $row['v'];
        }
      } else {
        $this->logCritical('failed to read settings: '.$this->getError());
      }
    }
    return $this->appSettings;
  }

  /** Compute average and maximum wind speed in km/h. */
  public function computeWindStats($desiredEndTimestampSeconds, $windowSeconds) {
    // Restrict to actually covered time, keeping window size if possible.
    $q = 'SELECT UNIX_TIMESTAMP(startup), UNIX_TIMESTAMP(upto) FROM coverage'
        .' ORDER BY startup DESC LIMIT 1';
    if (!($result = $this->query($q))) {
      return null;
    }
    $row = $result->fetch_row();
    if ($row == null) {
      return null;  // DB is empty
    }
    $startupTimestampSeconds = $row[0];
    $uptoTimestampSeconds = $row[1];
    $endTimestampMillis = min($desiredEndTimestampSeconds, $uptoTimestampSeconds) * 1000;
    $startTimestampMillis =
        max($endTimestampMillis - $windowSeconds * 1000, $startupTimestampSeconds * 1000);
    // TODO: Indicate if this deviates significantly from the desired range. Maybe return the
    // actual range?

    $q = 'SELECT ts FROM wind WHERE ts >= '.$startTimestampMillis.' AND ts <= '
        .$endTimestampMillis.' ORDER BY ts';
    if ($result = $this->query($q)) {
      $windStatsCalculator = new WindStatsCalculator($startTimestampMillis, $endTimestampMillis);
      while ($row = $result->fetch_row()) {
        $windStatsCalculator->nextTimestamp($row[0]);
      }
      // TODO: Also compute cumulative histogram here.
      return $windStatsCalculator->finalizeAndGetStats();
    }
    $this->logCritical('failed to compute wind stats: '.$this->getError());
    return null;
  }

  /** Print a table with database statistics (for debugging). */
  public function echoStats() {
    // Order to match $allTables.
    $sortKeys = array('startup', 'ts', 'ts', 'ts');
    $limits = array(5, 1, 1, 1);
    for ($i = 0; $i < count($sortKeys); ++$i) {
      $table = $this->allTables[$i];
      $sortKey = $sortKeys[$i];
      $q1 = 'SELECT * FROM '.$table.' ORDER BY '.$sortKey.' DESC LIMIT '.$limits[$i];
      $q2 = 'SELECT COUNT(*) FROM '.$table;
      if (($resultRecent = $this->query($q1)) && ($resultCount = $this->query($q2))) {
        $rowCount = $resultCount->fetch_row();
        echo '<p><table border="1"><tr><td>' . $table . '</td>';
        $count = 0;
        while ($row = $resultRecent->fetch_assoc()) {
          if ($count == 0) {
            foreach ($row as $k => $v) {
              echo '<td>' . $k . '</td>';
            }
            echo '<td>total</td>';
          }
          echo '</tr><tr><td>N-'.$count.'</td>';
          if (isset($row)) {
            foreach ($row as $k => $v) {
              echo '<td>' . $v . '</td>';
            }
          }
          echo '<td>' . $rowCount[0] . '</td>';
          $count++;
        }
      } else {
        echo '<b>' . $table . '</b>: ERROR: ' . $this->getError();
      }
      echo '</tr>';
      echo '</table></p>';
    }
  }

  public function echoSettings() {
    echo '<p><table border="1">';
    foreach ($this->getAppSettings() as $k => $v) {
      echo '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
    }
    echo '</table></p>';
  }

  // TODO: Remove testing methods.
  public function echoTempAverage($periodSeconds) {
    $minTimestampMillis = int((time() - $periodSeconds) * 1000 + 0.5);
    $q = 'SELECT ts, AVG(t) AS avg_t FROM temp WHERE ts >= "' . $minTimestampMillis . '"';
    if (!($result = $this->query($q))) {
      echo $this->getError();
      return;
    }
    echo '<table border="1">';
    while ($row = $result->fetch_assoc()) {
      echo '<tr><td>' . $row['ts'] . '</td><td>' . $row['avg_t'] . '</td></tr>';
    }
    echo '</table>';
  }

  public function echoTemp() {
    if (!($result = $this->query('SELECT * FROM temp ORDER BY ts DESC LIMIT 3'))) {
      echo $this->getError();
      return;
    }
    echo '<table border="1">';
    while ($row = $result->fetch_assoc()) {
      echo '<tr><td>' . formatTimestamp($row['ts'] / 1000) . '</td><td>' . $row['t'] . '</td></tr>';
    }
    echo '</table>';
  }

  public function echoMeta() {
    if (!($result = $this->query('SELECT * FROM meta ORDER BY ts DESC LIMIT 5'))) {
      echo $this->getError();
      return;
    }
    echo '<table border="1">';
    while ($row = $result->fetch_assoc()) {
      echo '<tr><td>'.$row['ts'].'</td><td>'.$row['cts'].'</td><td>'.$row['ip'].'</td><td>'
          .$row['stratum'].'</td></tr>';
    }
    echo '</table>';
  }
}
?>
