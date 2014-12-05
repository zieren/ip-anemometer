<?php
require_once 'common.php';


define('NO_WIND_DURATION', 10000);  # Revolutions longer than 10 seconds are considered 0 km/h.


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
  private $maxTimestamp = 0;
  private $avgKmh = 0;
  private $histogram = array();

  private $previousTimestamp;
  private $previousKmh = 0;  // assume 0 if we have no timestamps at all

  // Initialized in constructor:
  private $startTimestamp;
  private $endTimestamp;

  function __construct($startTimestamp, $endTimestamp) {
    $this->startTimestamp = $startTimestamp;
    $this->endTimestamp = $endTimestamp;
  }

  public function nextTimestamp($timestamp) {
    if ($this->phase == Phase::FINALIZED) {
      throw new Exception('cannot update stats - already finalized');
    }
    if ($this->phase == Phase::BEFORE_FIRST_TIMESTAMP) {
      $this->previousTimestamp = $timestamp;
      $this->phase = Phase::BEFORE_FIRST_KMH;
      return;
    }
    // Compute duration (i.e. weight) and speed.
    $duration = $timestamp - $this->previousTimestamp;
    $kmh = WindStatsCalculator::computeKmh($duration);
    if ($this->phase == Phase::BEFORE_FIRST_KMH) {
      $startSilenceDuration = $this->previousTimestamp - $this->startTimestamp;
      // Extrapolation using $kmh, or less if the start silence is longer.
      $startKmh = min($kmh, WindStatsCalculator::computeKmh($startSilenceDuration));
      $this->update($startKmh, $startSilenceDuration, $timestamp);
      $this->phase = Phase::STEADY_STATE;
    }
    $this->update($kmh, $duration, $timestamp);
    // Remember timestamp for next row.
    $this->previousTimestamp = $timestamp;
    // Remember km/h for extrapolation at the end.
    $this->previousKmh = $kmh;
  }

  private function update($kmh, $duration , $timestamp ) {
    // Update histogram.
    $this->histogram[intval($kmh)] += $duration;
    // Update maximum.
    if ($kmh > $this->maxKmh) {
      $this->maxKmh = $kmh;
      $this->maxTimestamp = $timestamp;
    }
    // Update average.
    $this->avgKmh += $kmh * $duration;
  }

  /** Finalizes computation and returns a WindStats object. */
  public function finalizeAndGetStats() {
    switch ($this->phase) {
    	case Phase::FINALIZED:
    	  throw new Exception('cannot finalize stats - already finalized');
    	case Phase::BEFORE_FIRST_TIMESTAMP:  // didn't initialize $this->previousTimestamp yet
    	  $this->previousTimestamp = $this->startTimestamp;
    	  break;
    }
    $endSilenceDuration = $this->endTimestamp - $this->previousTimestamp;
    // Extrapolation using $this->previousKmh, or less if the end silence is longer.
    $endKmh = min($this->previousKmh, WindStatsCalculator::computeKmh($endSilenceDuration));
    $this->update($endKmh, $endSilenceDuration, $this->previousTimestamp);
    $totalDuration = $this->endTimestamp - $this->startTimestamp;
    // Compute average.
    $this->avgKmh /= $totalDuration;
    // Convert histogram to relative values.
    foreach ($this->histogram as $k => $v) {
      $this->histogram[$k] = $v / $totalDuration;
    }
    ksort($this->histogram);
    $this->phase = Phase::FINALIZED;
    return new WindStats($this->avgKmh, $this->maxKmh, $this->maxTimestamp, $this->histogram,
        $this->startTimestamp, $this->endTimestamp);
  }

  /** Convert revolution duration to windspeed in km/h. */
  private static function computeKmh($duration) {
    // TODO: Extract magic values, including NO_WIND_DURATION above.
    if ($duration > NO_WIND_DURATION) {
      return 0;
    }
    $rps = durationToRps($duration);  // rotations per second
    return 1.761 / (1 + $rps) + 3.013 * $rps;
  }
}
?>
