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
    $this->allTables = array('coverage', 'wind', 'wind_a', 'temp', 'meta', 'settings');
    $this->mysqli = new mysqli('localhost', DB_USER, DB_PASS, DB_NAME);
    if ($this->mysqli->connect_errno) {
      $this->logCritical('failed to connect to MySQL: ('.$mysqli->connect_errno.') '
          .$this->mysqli->connect_error);
      exit(1);
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
        && $this->query(  // TODO: Remove precision mode table.
            'CREATE TABLE IF NOT EXISTS wind (ts BIGINT PRIMARY KEY)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS wind_a (start_ts BIGINT PRIMARY KEY, '.
            'end_ts BIGINT, avg FLOAT, max FLOAT, max_ts BIGINT, hist_id INT, buckets INT)')
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
   * Insert the specified data.
   *
   * @param array $samples Samples provided by the client. Each is the result of one
   *     Wind.get_sample() call on the client. Actual wind speed data may be absent, but coverage
   *     data must be present.
   */
  public function insertWind($samples) {
    foreach ($samples as $sample) {
      $stats = $sample[WIND_AGGREGATE_STATS_KEY];
      if ($stats) {
        $this->insertStats($stats);
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

  private function insertStats($stats) {
    // Insert histogram data first because we need the id-s in the hist table.
    $histogram = $stats[WIND_KEY_HIST];
    $histId = $this->insertHistogram($histogram);
    if (!$histId) {
      return;  // error already logged
    }
    $buckets = count($histogram);
    $q = 'INSERT INTO wind_a (start_ts, end_ts, avg, max, max_ts, hist_id, buckets) VALUES ('
        .$stats[WIND_KEY_START_TS].','.$stats[WIND_KEY_END_TS].','.$stats[WIND_KEY_AVG].','
        .$stats[WIND_KEY_MAX].','.$stats[WIND_KEY_MAX_TS].','.$histId.','.$buckets.')';
    $this->log->debug('QUERY: '.$q);
    if (!$this->query($q)) {
      $this->logCritical('failed to insert wind measurements: '.$this->getError());
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

  /**
   * Compute statistics for the specified time period.
   *
   * @param int $maxEndTimestamp Consider samples up to this end timestamp in millis (usually the
   *    current time).
   * @param int $windowDuration Length of the window to consider, in millis.
   * @param int $outputLength Maximum number of samples in time series (will be downsampled if
   *    required).
   * @return array An array indexed by WIND_KEY_*.
   */
  public function computeWindStats($maxEndTimestamp, $windowDuration, $outputLength) {
    $minStartTimestamp = $maxEndTimestamp - $windowDuration - WIND_MAX_LATENCY;
    $q = 'SELECT start_ts, end_ts, avg, max, max_ts, hist_id, buckets FROM wind_a WHERE '
        .'start_ts >= '.$minStartTimestamp.' AND start_ts <= '.$maxEndTimestamp
        .' AND end_ts <= '.$maxEndTimestamp
        .' ORDER BY start_ts DESC';
    $result = $this->checkQuery($q);
    if (!$result) return null;

    // Read samples in reverse chronological order until the desired duration is best approximated.
    // At the same time, compute running stats except histogram.
    $actualStartTimestamp = 0;
    $actualEndTimestamp = 0;
    $actualWindowDuration = 0;
    $minHistId = 0;
    $maxHistId = 0;
    $maxKmh = 0;
    $maxTimestamp = 0;
    $avgKmh = 0;
    $selectedSamples = array();
    $timeSeries = array();
    while ($sample = $result->fetch_assoc()) {
      // TODO: What about gaps?
      // Can we approximate the desired duration better by selecting this row?
      $sampleDuration = Database::getSampleDuration($sample);
      if (abs($actualWindowDuration - $windowDuration) <
          abs($actualWindowDuration + $sampleDuration - $windowDuration)) {
        break;
      }
      $timeSeries[] = array(  // order as per WIND_KEY_SAMPLE_*
          intval($sample['start_ts']),
          intval($sample['end_ts']),
          floatval($sample['avg']),
          floatval($sample['max'])
      );
      $selectedSamples[] = $sample;
      // Update times and IDs.
      $actualWindowDuration += $sampleDuration;
      $actualStartTimestamp = $sample['start_ts'];
      if (!$actualEndTimestamp) {
        $actualEndTimestamp = $sample['end_ts'];
      }
      $minHistId = $sample['hist_id'];
      if (!$maxHistId) {
        $maxHistId = $minHistId + $sample['buckets'] - 1;
      }
      // Update running stats except histogram.
      if ($sample['max'] > $maxKmh) {
        $maxKmh = $sample['max'];
        $maxTimestamp = $sample['max_ts'];
      }
      $avgKmh += $sample['avg'] * $sampleDuration;
    }
    if (count($selectedSamples) == 0) {
      return null;
    }
    $avgKmh /= $actualWindowDuration;

    // Compute histogram.
    $q = 'SELECT * from hist WHERE id >= '.$minHistId.' AND id <= '.$maxHistId.' ORDER BY id DESC';
    $result = $this->checkQuery($q);
    $histogram = array();
    $i = 0;
    $sampleDuration = Database::getSampleDuration($selectedSamples[0]);
    while ($bucket = $result->fetch_assoc()) {
      if ($bucket['id'] < $selectedSamples[$i]['hist_id']) {  // belongs to next (older) sample
        ++$i;
        $sampleDuration = Database::getSampleDuration($selectedSamples[$i]);
      }
      $histogram[$bucket['v']] += $bucket['p'] * $sampleDuration;
    }
    foreach ($histogram as $v => $p) {
      $histogram[$v] /= $actualWindowDuration;
    }
    ksort($histogram);

    return array(
        WIND_KEY_AVG => $avgKmh,  // already float
        WIND_KEY_MAX => floatval($maxKmh),
        WIND_KEY_MAX_TS => intval($maxTimestamp),
        WIND_KEY_HIST => $histogram,
        WIND_KEY_START_TS => intval($actualStartTimestamp),
        WIND_KEY_END_TS => intval($actualEndTimestamp),
        WIND_KEY_TIME_SERIES => Database::downsampleWind($timeSeries, $outputLength)
    );
  }

  private static function getSampleDuration($sample) {
    // We rely on the difference fitting into an integer.
    return $sample['end_ts'] - $sample['start_ts'];
  }

  private static function downsampleWind($input, $outputLength) {
    $inputLength = count($input);
    $output = array();
    if ($inputLength <= $outputLength || $inputLength <= 1) {  // nothing to downsample
      foreach ($input as $sample) {
        $output[] = array(
            Database::center($sample[WIND_KEY_SAMPLE_START_TS], $sample[WIND_KEY_SAMPLE_END_TS]),
            $sample[WIND_KEY_SAMPLE_AVG],
            $sample[WIND_KEY_SAMPLE_MAX],
        );
      }
      return $output;
    }
    $startTs = $input[$inputLength - 1][WIND_KEY_SAMPLE_START_TS];
    $endTs = $input[0][WIND_KEY_SAMPLE_END_TS];
    $wi = 0;  // window index
    $windowStart = $startTs;
    $windowEnd = Database::getWindowEnd($startTs, $endTs, $outputLength, $wi);
    $window = Database::newWindow($windowStart, $windowEnd);
    $windowDuration = 0;  // actually covered time in window (there might be gaps)
    $i = $inputLength - 1;  // order is by decreasing timestamp
    while ($i >= 0) {
      // Shortcuts.
      $inputStart = $input[$i][WIND_KEY_SAMPLE_START_TS];
      $inputEnd = $input[$i][WIND_KEY_SAMPLE_END_TS];
      $inputCenter = Database::center($inputStart, $inputEnd);
      $inputAvg = $input[$i][WIND_KEY_SAMPLE_AVG];
      $inputMax = $input[$i][WIND_KEY_SAMPLE_MAX];
      while (true) {
        $overlap = min($windowEnd, $inputEnd) - max($windowStart, $inputStart);
//         echo '<p>i='.$i.',is='.($inputStart%1000000).',ie='.($inputEnd%1000000)
//             .',ws='.($windowStart%1000000).',we='.($windowEnd%1000000)
//             .',ol='.$overlap.'</p>';
        if ($overlap >= 0) {
          break;
        }
        // If there is a gap in the input the overlap may be negative. Advance the window to catch
        // up.
        $windowStart = $windowEnd;
        $windowEnd = Database::getWindowEnd($startTs, $endTs, $outputLength, ++$wi);
      }
      $windowDuration += $overlap;
      $window[WIND_KEY_SAMPLE_AVG] += $inputAvg * $overlap;
      // Consider the maximum if the window includes the center of the current sample.
      if ($windowStart <= $inputCenter && $inputCenter < $windowEnd) {
        $window[WIND_KEY_SAMPLE_MAX] = max($window[WIND_KEY_SAMPLE_MAX], $inputMax);
      }
      // If the current input reaches into the next window, or is the last input, output the sample
      // and proceed to the next window.
      if ($inputEnd > $windowEnd || $i == 0) {
        $output[] = array(
        	Database::center($windowStart, $windowEnd),
            $window[WIND_KEY_SAMPLE_AVG] / $windowDuration,
            $window[WIND_KEY_SAMPLE_MAX]
        );
        if ($i == 0) {
          break;
        }
        $windowStart = $windowEnd;
        $windowEnd = Database::getWindowEnd($startTs, $endTs, $outputLength, ++$wi);
        $windowDuration = 0;
        $window = Database::newWindow($windowStart, $windowEnd);
      } else {  // next input still overaps with the current window
        $i--;
      }
    }
    return $output;
  }

  private static function center($start, $end) {
    return intval(($end - $start) / 2 + $start);
  }

  private static function getWindowEnd($startTs, $endTs, $outputLength, $wi) {
    return intval((($endTs - $startTs) / $outputLength) * ($wi + 1) + $startTs);
  }

  private static function newWindow($windowStart, $windowEnd) {
    return array($windowStart, $windowEnd, 0, -1);  // number of elements as in WIND_KEY_SAMPLE_*
  }

  private static function downsampleTemperature($input, $outputLength) {
    $inputLength = count($input);
    if ($inputLength <= $outputLength || $inputLength <= 1) {  // nothing to downsample
      return $input;
    }
    $output = array();
    $startTs = $input[0][0];
    $endTs = $input[$inputLength - 1][0];
    $bucketIndex = 0;
    $nextBucket = Database::getNextBucket($startTs, $endTs, $outputLength, $bucketIndex);
    $temps = array();
    $timestamps = array();
    for ($i = 0; $i < $inputLength; ++$i) {
      $ts = $input[$i][0];
      $temp = $input[$i][1];
      if ($ts < $nextBucket) {
        $temps[] = $temp;
        $timestamps[] = $ts;
      }
      if ($ts >= $nextBucket || $i + 1 == $inputLength) {
        if ($temps) {
          $output[] = array(
            intval(Database::average($timestamps)),
            floatval(Database::average($temps)));
        }
      }
      if ($ts >= $nextBucket) {
        $temps = array($temp);
        $timestamps = array($ts);
        $nextBucket = Database::getNextBucket($startTs, $endTs, $outputLength, ++$bucketIndex);
      }
    }
    return $output;
  }

  private static function getNextBucket($startTs, $endTs, $outputLength, $bucketIndex) {
    if ($bucketIndex + 1 == $outputLength) {
      return PHP_INT_MAX;  // last bucket catches all
    }
    return intval((($endTs - $startTs) / $outputLength) * ($bucketIndex + 1) + $startTs);
  }

  private static function average($values) {
    $average = 0;
    $n = count($values);
    foreach ($values as $v) {
      $average += $v / $n;
    }
    return $average;
  }

  public function readTemperature($endTimestamp, $windowDuration, $timeSeriesPoints) {
    $startTimestamp = $endTimestamp - $windowDuration;
    $q = 'SELECT ts, t FROM temp WHERE ts >= '.$startTimestamp
        .' AND ts <= '.$endTimestamp.' ORDER BY ts';
    $temp = array();
    if ($result = $this->query($q)) {
      while ($row = $result->fetch_row()) {
        $temp[] = array(intval($row[0]), floatval($row[1]));
      }
      return Database::downsampleTemperature($temp, $timeSeriesPoints);
    }
    $this->logCritical('failed to compute temp stats: "'.$q.'" -> '.$this->getError());
    return null;
  }

  /** Print a table with database statistics (for debugging). */
  public function echoStats() {
    // Order of $sortKeys and $limits must match $allTables.
    $sortKeys = array('startup', 'ts', 'start_ts', 'ts', 'ts');
    $limits = array(5, 1, 1, 1, 1);
    // List of column names that store timestamps in millis.
    $timestampColumns = array('ts', 'cts', 'start_ts', 'end_ts', 'startup', 'upto');
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
              echo '<td>'.(in_array($k, $timestampColumns) ? formatTimestamp($v) : $v).'</td>';
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

  // TODO: Use this.
  private function checkQuery($query, $message = null) {
    $result = $this->query($query);
    if (!$result) {
      if ($message) {
        $extendedMessage = $message.' -- ';
      }
      $extendedMessage .= 'Error: '.$this->getError().' -- Query: '.$query;
      $this->log->critical($extendedMessage);
      // TODO: exit($extendedMessage);
    }
    return $result;
  }
}
?>
