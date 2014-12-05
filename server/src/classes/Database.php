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
            'CREATE TABLE IF NOT EXISTS wind_a (start_ts BIGINT PRIMARY KEY, '.
            'end_ts BIGINT, avg FLOAT, max FLOAT, max_ts BIGINT, hist_from INT, hist_to INT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS hist (id INT PRIMARY KEY AUTO_INCREMENT, v INT, p FLOAT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS coverage (startup BIGINT PRIMARY KEY, upto BIGINT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS meta (ts BIGINT PRIMARY KEY, cts BIGINT, '.
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

  /**
   * @param array $samples Samples provided by the client. Each is the result of one
   *     Wind.get_sample() call on the client. Precision and/or aggregate mode data may be present.
   *     All data present will be inserted. It is an error to specify an empty array.
   */
  public function insertWind($samples) {
    $q = '';  // for precision mode, collect all timestamps from all samples
    // TODO: Limit query size.
    foreach ($samples as $sample) {
      // Process precision mode data (if present).
      $revolutions = $sample[WIND_REVOLUTIONS_KEY];
      if ($revolutions) {
        foreach ($revolutions as $ts) {
          if ($q != '') {
            $q .= ',';
          }
          $q .= '('.$ts.')';
        }
      }
      // Insert aggregate mode stats (if present).
      $stats = $sample[WIND_AGGREGATE_STATS_KEY];
      if ($stats) {
        $this->insertAggregateStats($stats);
      }
    }

    // Insert precision mode data (if present).
    if ($q != '') {  // not precision mode, or no single revolution recorded (i.e. no wind)
      $q = 'REPLACE INTO wind (ts) VALUES '.$q;
      $this->log->debug('QUERY: '.$q);
      if (!$this->query($q)) {
        $this->logCritical('failed to insert wind measurements: '.$this->getError());
      }
    }

    // Insert coverage. We're only interested in the most recent timestamps and ignore those of
    // previous failed upload attempts (if any).
    $latestSample = $samples[count($samples) - 1];
    $startupTimestamp = $latestSample[WIND_STARTUP_TIME_KEY];
    $upToTimestamp = $latestSample[WIND_UP_TO_TIME_KEY];
    $q = 'REPLACE INTO coverage (startup, upto) VALUES ('.$startupTimestamp.','.$upToTimestamp.')';
    if (!$this->query($q)) {
      $this->logCritical('failed to insert coverage timestamps: '.$this->getError());
    }
  }

  private function insertAggregateStats($stats) {
    // Insert histogram data first because we need the keys in the hist table.
    $hist = $stats[WIND_KEY_HIST];
    $hist_from = $this->insertHistogram($hist);
    if (!$hist_from) {
      return;  // error already logged
    }
    $hist_to = $hist_from + count($hist) - 1;
    $q = 'INSERT INTO wind_a (start_ts, end_ts, avg, max, max_ts, hist_from, hist_to) VALUES ('
        .$stats[WIND_KEY_START_TS].','.$stats[WIND_KEY_END_TS].','.$stats[WIND_KEY_AVG].','
        .$stats[WIND_KEY_MAX].','.$stats[WIND_KEY_MAX_TS].','.$hist_from.','.$hist_to.')';
    $this->log->debug('QUERY: '.$q);
    if (!$this->query($q)) {
      $this->logCritical('failed to insert aggregate wind measurements: '.$this->getError());
    }
  }

  /** Returns the first (lowest) AUTO_INCREMENT ID generated, or NULL on error. */
  public function insertHistogram($histogram) {
    $q = '';
    foreach ($histogram as $v => $p) {  // v=speed, p=percent
      if ($q != '') {
        $q .= ',';
      }
      $q .= '('.$v.','.$p.')';
    }
    $q = 'INSERT INTO hist (v, p) VALUES '.$q;
    $this->log->debug('QUERY: '.$q);
    $result = $this->query($q);
    if (!$result) {
      $this->logCritical('failed to insert histogram: '.$this->getError());
      return null;
    }

    $result = $this->query('SELECT LAST_INSERT_ID()');
    if (!$result) {
      $this->logCritical('failed to obtain LAST_INSERT_ID: '.$this->getError());
      return null;
    }
    $row = $result->fetch_row();
    return $row[0];
  }

  public function insertMetadata($meta, $ip) {
    $q = 'REPLACE INTO meta (ts, cts, stratum, fails, ip) VALUES ('.timestamp().','.
        $meta[CLIENT_TIMESTAMP_KEY].','.$meta[STRATUM_KEY].','.$meta[FAILED_UPLOADS_KEY].',"'
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
  public function computeWindStats($desiredEndTimestamp, $windowDuration) {
    // Restrict to actually covered time, keeping window size if possible.
    $q = 'SELECT startup, upto FROM coverage ORDER BY startup DESC LIMIT 1';
    if (!($result = $this->query($q))) {
      return null;
    }
    $row = $result->fetch_row();
    if ($row == null) {
      return null;  // DB is empty
    }
    $startupTimestamp = $row[0];
    $uptoTimestamp = $row[1];
    $endTimestamp = min($desiredEndTimestamp, $uptoTimestamp);
    $startTimestamp = max($endTimestamp - $windowDuration, $startupTimestamp);
    // TODO: Indicate if this deviates significantly from the desired range. Maybe return the
    // actual range?

    $q = 'SELECT ts FROM wind WHERE ts >= '.$startTimestamp
        .' AND ts <= '.$endTimestamp.' ORDER BY ts';
    if ($result = $this->query($q)) {
      $windStatsCalculator = new WindStatsCalculator($startTimestamp, $endTimestamp);
      while ($row = $result->fetch_row()) {
        $windStatsCalculator->nextTimestamp($row[0]);
      }
      // TODO: Also compute cumulative histogram here.
      return $windStatsCalculator->finalizeAndGetStats();
    }
    $this->logCritical('failed to compute wind stats: "'.$q.'" -> '.$this->getError());
    return null;
  }

  /** Print a table with database statistics (for debugging). */
  public function echoStats() {
    // Order to match $allTables.
    $sortKeys = array('startup', 'ts', 'ts', 'ts');
    $timestamps = array('ts', 'cts', 'startup', 'upto');
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
              echo '<td>'.(in_array($k, $timestamps) ? formatTimestamp($v) : $v).'</td>';
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
  public function echoTemp() {
    if (!($result = $this->query('SELECT * FROM temp ORDER BY ts DESC LIMIT 3'))) {
      echo $this->getError();
      return;
    }
    echo '<table border="1">';
    while ($row = $result->fetch_assoc()) {
      echo '<tr><td>' . formatTimestamp($row['ts']) . '</td><td>' . $row['t'] . '</td></tr>';
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
