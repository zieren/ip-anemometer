<?php

class WindStats {
  private $avgKmh;
  private $maxKmh;
  private $maxTimestampSeconds;
  private $histogram;
  private $startTimestampSeconds;
  private $endTimestampSeconds;

  function __construct($avgKmh, $maxKmh, $maxTimestampSeconds, $histogram, $startTimestampSeconds,
      $endTimestampSeconds) {
    $this->avgKmh = $avgKmh;
    $this->maxKmh = $maxKmh;
    $this->maxTimestampSeconds = $maxTimestampSeconds;
    $this->histogram = $histogram;
    $this->startTimestampSeconds = $startTimestampSeconds;
    $this->endTimestampSeconds = $endTimestampSeconds;
  }

  public function getAvgKmh() { return $this->avgKmh; }
  public function getMaxKmh() { return $this->maxKmh; }
  public function getMaxTimestampSeconds() { return $this->maxTimestampSeconds; }
  public function getHistogram() { return $this->histogram; }
  public function getStartTimestampSeconds() { return $this->startTimestampSeconds; }
  public function getEndTimestampSeconds() { return $this->endTimestampSeconds; }
}

?>