<?php
require_once 'common.php';
require_once 'config.php';

// TODO: Better separation of DB and business logic.

abstract class DownsampleMode {
  const AVERAGE = 1;
  const MIN_MAX = 2;
}

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
    $this->log = Logger::Instance();
    $this->allTables = array('temp', 'wind', 'hist', 'link', 'meta', 'settings');
    $this->mysqli = new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
    if ($this->mysqli->connect_errno) {
      $this->logCritical('failed to connect to MySQL: ('.$mysqli->connect_errno.') '
          .$this->mysqli->connect_error);
      exit(1);
    }
    assert($this->mysqli->select_db(DB_NAME));
    // TODO: Read config and set log level.
  }

  public function beginTransaction() {
    $this->query('START TRANSACTION');
  }

  public function commit() {
    $this->query('COMMIT');
  }

  public function rollback() {
    $this->query('ROLLBACK');
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
    if ($this->query('SET storage_engine=INNODB')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS temp (ts BIGINT PRIMARY KEY, t FLOAT NOT NULL)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS wind (start_ts BIGINT PRIMARY KEY, '
            .'end_ts BIGINT, avg FLOAT, max FLOAT, max_ts BIGINT, hist_id INT, buckets INT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS hist (id INT PRIMARY KEY AUTO_INCREMENT, v INT, p FLOAT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS link (ts BIGINT PRIMARY KEY, nwtype VARCHAR(20), '
            .'strength TINYINT, upload BIGINT, download BIGINT)')
        && $this->query(
            'CREATE TABLE IF NOT EXISTS meta (ts BIGINT PRIMARY KEY, upto BIGINT, cts BIGINT, '
            .'stratum INT, fails INT, ip VARCHAR(15))')
        && $this->query(
            // TODO: Rename settings to config
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

  public function insertLinkStatus($linkStatus) {
    if (count($linkStatus) == 0) {
      $this->log->warning('received empty link status');
      return;
    }
    $q = '';
    foreach ($linkStatus as $v) {
      if ($q != '') {
        $q .= ',';
      }
      $q .= '('.$v['ts'].',"'.$v['nwtype'].'",'.$v['strength'].
          ','.$v['upload'].','.$v['download'].')';
    }

    $q = 'REPLACE INTO link (ts, nwtype, strength, upload, download) VALUES '.$q;
    $this->log->debug('QUERY: '.$q);
    if (!$this->query($q)) {
      $this->logCritical('failed to insert link status: ' . $this->getError());
    }
  }

  /**
   * Insert wind data.
   *
   * @param array $samples Samples provided by the client. Each is the result of one
   *     Wind.get_sample() call on the client.
   *
   * @return integer The latest end timestamp (for latency computation). 0 indicates no data.
   */
  public function insertWind($samples) {
    foreach ($samples as $stats) {
      // Insert histogram data first because we need the id-s in the hist table.
      $histogram = $stats['hist'];
      $histId = $this->insertHistogram($histogram);
      if (!$histId) {
        return;  // error already logged
      }
      $buckets = count($histogram);
      $q = 'REPLACE INTO wind (start_ts, end_ts, avg, max, max_ts, hist_id, buckets) VALUES ('
          .$stats['start_ts'].','.$stats['end_ts'].','.$stats['avg'].','
          .$stats['max'].','.$stats['max_ts'].','.$histId.','.$buckets.')';
      $this->log->debug('QUERY: '.$q);
      if (!$this->query($q)) {
        $this->logCritical('failed to insert wind measurements: '.$this->getError());
        return;
      }
    }

    $c = count($samples);
    return $c > 0 ? $samples[$c - 1]['end_ts'] : 0;
  }

  /** Returns the first (lowest) AUTO_INCREMENT ID generated, or NULL on error. */
  public function insertHistogram($histogram) {
    ksort($histogram);  // not required, but makes the table easier to read (for humans)
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

  public function insertMetadata($meta) {
    $q = 'REPLACE INTO meta (ts, upto, cts, stratum, fails, ip) VALUES ('.timestamp().','
        .$meta['upto'].','.$meta['cts'].','.$meta[STRATUM_KEY].','
        .$meta[FAILED_UPLOADS_KEY].',"'.$_SERVER['REMOTE_ADDR'].'")';
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
    unset($this->appSettings);
  }

  /** Deletes the specified setting. */
  public function clearSetting($key) {
    $q = 'DELETE FROM settings WHERE k="'.$key.'"';
    if (!$this->query($q)) {
      $this->logCritical('failed to clear setting ('.$key.'): '.$this->getError());
    }
    unset($this->appSettings);
  }

  /** Returns application settings. Lazily initialized. */
  public function getAppSettings() {
    if (!isset($this->appSettings)) {
      $this->appSettings = array();
      if ($result = $this->query('SELECT k, v FROM settings')) {
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
   * @return array An array containing some scalar and the following non-scalar stats:
   *    'hist': An array of int(km/h) -> percentage.
   *    'time_series': A list of 3-tuples (timestamp, avg, max).
   */
  public function computeWindStats($maxEndTimestamp, $windowDuration, $outputLength) {
    $minStartTimestamp = $maxEndTimestamp - $windowDuration - WIND_MAX_LATENCY;
    $q = 'SELECT start_ts, end_ts, avg, max, max_ts, hist_id, buckets FROM wind WHERE '
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
      $timeSeries[] = array(  // order as per WIND_SAMPLE_*
          intval($sample['start_ts']),
          intval($sample['end_ts']),
          floatval($sample['avg']),
          floatval($sample['max'])  // TODO: Use max_ts for the max time series.
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
    $q = 'SELECT id, v, p from hist WHERE id >= '.$minHistId.' AND id <= '.$maxHistId
        .' ORDER BY id DESC';
    $result = $this->checkQuery($q);
    $histogram = array();
    $i = 0;
    $sampleDuration = Database::getSampleDuration($selectedSamples[0]);
    while ($bucket = $result->fetch_assoc()) {
      if ($bucket['id'] < $selectedSamples[$i]['hist_id']) {  // belongs to next (older) sample
        ++$i;
        $sampleDuration = Database::getSampleDuration($selectedSamples[$i]);
      }
      if ($bucket['id'] >= $selectedSamples[$i]['hist_id'] + $selectedSamples[$i]['buckets']) {
        // Without transaction this can happen if inserting the histogram succeeded but writing the
        // wind data failed. But even with transaction the server's "status: ok" reply might not
        // reach the client, so the client will resend the same data, which will overwrite the
        // previous data in all tables except hist, where it will add new rows.
        // In both cases there will be "orphaned" entries in the hist table that do not belong to
        // any row in the wind table. Skip them here.
        continue;
      }
      $histogram[$bucket['v']] += $bucket['p'] * $sampleDuration;
    }
    foreach ($histogram as $v => $p) {
      $histogram[$v] /= $actualWindowDuration;
    }
    ksort($histogram);

    return array(
        'avg' => $avgKmh,  // already float
        'max' => floatval($maxKmh),
        'max_ts' => intval($maxTimestamp),
        'hist' => $histogram,
        'start_ts' => intval($actualStartTimestamp),
        'end_ts' => intval($actualEndTimestamp),
        'time_series' => Database::downsampleWind($timeSeries, $outputLength)
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
            Database::center($sample[WIND_SAMPLE_START_TS], $sample[WIND_SAMPLE_END_TS]),
            $sample[WIND_SAMPLE_AVG],
            $sample[WIND_SAMPLE_MAX],
        );
      }
      return $output;
    }
    $startTs = $input[$inputLength - 1][WIND_SAMPLE_START_TS];
    $endTs = $input[0][WIND_SAMPLE_END_TS];
    $wi = 0;  // window index
    $windowStart = $startTs;
    $windowEnd = Database::getWindowEnd($startTs, $endTs, $outputLength, $wi);
    $window = Database::newWindow($windowStart, $windowEnd);
    $windowDuration = 0;  // actually covered time in window (there might be gaps)
    $i = $inputLength - 1;  // order is by decreasing timestamp
    while ($i >= 0) {
      // Shortcuts.
      $inputStart = $input[$i][WIND_SAMPLE_START_TS];
      $inputEnd = $input[$i][WIND_SAMPLE_END_TS];
      $inputCenter = Database::center($inputStart, $inputEnd);
      $inputAvg = $input[$i][WIND_SAMPLE_AVG];
      $inputMax = $input[$i][WIND_SAMPLE_MAX];
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
      $window[WIND_SAMPLE_AVG] += $inputAvg * $overlap;
      // Consider the maximum if the window includes the center of the current sample.
      if ($windowStart <= $inputCenter && $inputCenter < $windowEnd) {
        $window[WIND_SAMPLE_MAX] = max($window[WIND_SAMPLE_MAX], $inputMax);
      }
      // If the current input reaches into the next window, or is the last input, output the sample
      // and proceed to the next window.
      if ($inputEnd > $windowEnd || $i == 0) {
        $output[] = array(
            Database::center($windowStart, $windowEnd),
            $window[WIND_SAMPLE_AVG] / $windowDuration,
            $window[WIND_SAMPLE_MAX]
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
    return array($windowStart, $windowEnd, 0, -1);  // number of elements as in WIND_SAMPLE_*
  }

  private static function downsampleTimeSeries($input, $outputLength,
      $mode = DownsampleMode::AVERAGE) {
    $inputLength = count($input);
    if ($inputLength <= $outputLength || $inputLength <= 1) {  // nothing to downsample
      return $input;
    }
    reset($input);
    $startTs = key($input);
    end($input);
    $endTs = key($input);
    $bucketIndex = 0;
    $bucketEndTs = Database::getBucketEndTs($startTs, $endTs, $outputLength, $bucketIndex);
    $values = array();
    $timestamps = array();
    $output = array();
    foreach ($input as $ts => $value) {
      // Still in current bucket?
      if ($ts < $bucketEndTs) {
        $values[] = $value;
        $timestamps[] = $ts;
      }
      // Output the bucket when it's finished or at the end of the iteration.
      if ($ts >= $bucketEndTs || $ts === $endTs) {
        if ($values) {
          $output[intval(Database::average($timestamps) + 0.5)] =
              $mode === DownsampleMode::AVERAGE
              ? Database::average($values)
              : Database::minMax($values);  // DownsampleMode::MIN_MAX
        }
      }
      // Start a new bucket?
      if ($ts >= $bucketEndTs) {
        $values = array($value);
        $timestamps = array($ts);
        while ($ts >= $bucketEndTs) {  // skip empty buckets, if any
          $bucketEndTs = Database::getBucketEndTs($startTs, $endTs, $outputLength, ++$bucketIndex);
        }
      }
    }
    return $output;
  }

  /**
   * Return the end timestamp (exclusive) of the current bucket, or PHP_INT_MAX if the current
   * bucket is the last bucket.
   */
  private static function getBucketEndTs($startTs, $endTs, $outputLength, $bucketIndex) {
    if ($bucketIndex + 1 == $outputLength) {
      return PHP_INT_MAX;  // last bucket catches all
    }
    return intval((($endTs - $startTs) / $outputLength) * ($bucketIndex + 1) + $startTs);
  }

  /** Compute the average of the specified array in a numerically stable way. */
  private static function average($values) {
    $average = 0;
    $n = count($values);
    foreach ($values as $v) {
      $average += $v / $n;
    }
    return $average;
  }

  private static function minMax($values) {
    return array(min($values), max($values));
  }

  public function readTemperature($endTimestamp, $windowDuration, $timeSeriesPoints) {
    $startTimestamp = $endTimestamp - $windowDuration;
    $q = 'SELECT ts, t FROM temp WHERE ts >= '.$startTimestamp
        .' AND ts <= '.$endTimestamp.' ORDER BY ts';
    $temp = array();
    if ($result = $this->query($q)) {
      while ($row = $result->fetch_row()) {
        $temp[intval($row[0])] = floatval($row[1]);
      }
      return Database::downsampleTimeSeries($temp, $timeSeriesPoints);
    }
    $this->logCritical('failed to read temperature: "'.$q.'" -> '.$this->getError());
    return null;
  }

  public function readSignalStrength($endTimestamp, $windowDuration, $timeSeriesPoints) {
    $startTimestamp = $endTimestamp - $windowDuration;
    $q = 'SELECT ts, strength FROM link WHERE ts >= '.$startTimestamp
        .' AND ts <= '.$endTimestamp.' ORDER BY ts';
    $strength = array();
    if ($result = $this->query($q)) {
      while ($row = $result->fetch_row()) {
        $strength[intval($row[0])] = intval($row[1]);
      }
      return Database::downsampleTimeSeries($strength, $timeSeriesPoints);
    }
    $this->logCritical('failed to read signal strength: "'.$q.'" -> '.$this->getError());
    return null;
  }

  public function readNetworkType($endTimestamp, $windowDuration) {
    $startTimestamp = $endTimestamp - $windowDuration;
    $q = 'SELECT nwtype FROM link WHERE ts >= '.$startTimestamp.' AND ts <= '.$endTimestamp;
    $nwtypes = array();
    if ($result = $this->query($q)) {
      while ($row = $result->fetch_row()) {
        $nwtypes[$row[0]] = $nwtypes[$row[0]] + 1;
      }
      return $nwtypes;
    }
    $this->logCritical('failed to read network types: "'.$q.'" -> '.$this->getError());
    return null;
  }

  public function readTransferVolume() {
    $q = 'SELECT upload, download FROM link ORDER BY ts DESC';
    if ($result = $this->query($q)) {
      if ($row = $result->fetch_row()) {
        $upload = intval($row[0]);
        $download = intval($row[1]);
      } else {
        $upload = 0;
        $download = 0;
      }
      return array('upload' => $upload, 'download' => $download);
    }
    $this->logCritical('failed to read transfer volume: "'.$q.'" -> '.$this->getError());
    return null;
  }

  public function readLag($endTimestamp, $windowDuration, $timeSeriesPoints) {
    $startTimestamp = $endTimestamp - $windowDuration;
    // TODO: Filter rows with bad stratum (possibly require that the previous row is already good).
    $q = 'SELECT ts, stratum, upto FROM meta WHERE ts >= '
        .$startTimestamp.' AND ts <= '.$endTimestamp.' ORDER BY ts';
    $lag = array();
    $previousUpto = 0;
    if ($result = $this->query($q)) {
      while ($row = $result->fetch_assoc()) {
        $upto = $row['upto'];
        if (!$upto) {  // gaps occur when no wind sample was present in the upload
          continue;
        }
        $ts = $row['ts'];
        // Synthesize one record just (1ms) prior to the upload to indicate the maximum lag.
        if ($previousUpto) {
          $lag[$ts - 1] = $ts - $previousUpto;
        }
        $lag[$ts] = $ts - $upto;
        $previousUpto = $upto;
      }
      // Add the current lag (which might be significant).
      if ($previousUpto) {
        $ts = timestamp();
        $lag[$ts] = $ts - $previousUpto;
      }
      return Database::downsampleTimeSeries($lag, $timeSeriesPoints, DownsampleMode::MIN_MAX);
    }
    $this->logCritical('failed to read lag: "'.$q.'" -> '.$this->getError());
    return null;
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
