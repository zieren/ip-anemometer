<?php
require_once 'common.php';

abstract class Phase {
  const BEFORE_FIRST_TIMESTAMP = 0;
  const BEFORE_FIRST_KMH = 1;
  const STEADY_STATE = 2;
  const FINALIZED = 3;
}

// TODO: Improve comments.

/**
 * Calculates wind stats from a sequence of timestamps representing revolutions, and a start/end
 * timestamp pair. This should match WindStatsCalculator on the client.
 */
class WindStatsCalculator {
  private $phase = Phase::BEFORE_FIRST_TIMESTAMP;

  private $maxKmh = 0;
  private $maxTimestampSeconds = 0;
  private $avgKmh = 0;
  private $histogram = array();

  private $previousTimestampMillis;
  private $previousKmh = 0;  // assume 0 if we have no timestamps at all

  // Initialized in constructor:
  private $startTimestampMillis;
  private $endTimestampMillis;

  function __construct($startTimestampMillis, $endTimestampMillis) {
    $this->startTimestampMillis = $startTimestampMillis;
    $this->endTimestampMillis = $endTimestampMillis;
  }

  public function nextTimestamp($timestampMillis) {
    if ($this->phase == Phase::FINALIZED) {
      throw new Exception('cannot update stats - already finalized');
    }
    if ($this->phase == Phase::BEFORE_FIRST_TIMESTAMP) {
      $this->previousTimestampMillis = $timestampMillis;
      $this->phase = Phase::BEFORE_FIRST_KMH;
      return;
    }
    // Compute duration (i.e. weight) and speed.
    $durationSeconds = ($timestampMillis - $this->previousTimestampMillis) / 1000;
    $kmh = WindStatsCalculator::computeKmh($durationSeconds);
    if ($this->phase == Phase::BEFORE_FIRST_KMH) {
      $startSilenceDurationSeconds =
          ($this->previousTimestampMillis - $this->startTimestampMillis) / 1000;
      // Extrapolation using $kmh, or less if the start silence is longer.
      $startKmh = min($kmh, WindStatsCalculator::computeKmh($startSilenceDurationSeconds));
      $this->update($startKmh, $startSilenceDurationSeconds, $timestampMillis);
      $this->phase = Phase::STEADY_STATE;
    }
    $this->update($kmh, $durationSeconds, $timestampMillis);
    // Remember timestamp for next row.
    $this->previousTimestampMillis = $timestampMillis;
    // Remember km/h for extrapolation at the end.
    $this->previousKmh = $kmh;
  }

  private function update($kmh, $durationSeconds, $timestampMillis) {
    // Update histogram.
    $this->histogram[intval($kmh)] += $durationSeconds;
    // Update maximum.
    if ($kmh > $this->maxKmh) {
      $this->maxKmh = $kmh;
      $this->maxTimestampSeconds = $timestampMillis / 1000;
    }
    // Update average.
    $this->avgKmh += $kmh * $durationSeconds;
  }

  /** Finalizes computation and returns a WindStats object. */
  public function finalizeAndGetStats() {
    switch ($this->phase) {
    	case Phase::FINALIZED:
    	  throw new Exception('cannot finalize stats - already finalized');
    	case Phase::BEFORE_FIRST_TIMESTAMP:  // didn't initialize $this->previousTimestamp yet
    	  $this->previousTimestampMillis = $this->startTimestampMillis;
    	  break;
    }
    $endSilenceDurationSeconds =
        ($this->endTimestampMillis - $this->previousTimestampMillis) / 1000;
    // Extrapolation using $this->previousKmh, or less if the end silence is longer.
    $endKmh = min($this->previousKmh, WindStatsCalculator::computeKmh($endSilenceDurationSeconds));
    $this->update($endKmh, $endSilenceDurationSeconds, $this->previousTimestampMillis);
    $totalDurationSeconds = ($this->endTimestampMillis - $this->startTimestampMillis) / 1000;
    // Compute average.
    $this->avgKmh /= $totalDurationSeconds;
    // Convert histogram to relative values.
    foreach ($this->histogram as $k => $v) {
      $this->histogram[$k] = $v / $totalDurationSeconds;
    }
    ksort($this->histogram);
    $this->phase = Phase::FINALIZED;
    return new WindStats($this->avgKmh, $this->maxKmh, $this->maxTimestampSeconds, $this->histogram,
        $this->startTimestampMillis / 1000, $this->endTimestampMillis / 1000);
  }

  /** Convert revolution duration to windspeed in km/h. */
  private static function computeKmh($durationSeconds) {
    // TODO: Extract magic values.
    $rps = 1 / $durationSeconds;  // rotations per second
    return $durationSeconds >= 10 ? 0 : (1.761 / (1 + $rps) + 3.013 * $rps);
  }
}
?>
