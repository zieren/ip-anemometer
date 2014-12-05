<?php

class WindStats {
  private $avgKmh;
  private $maxKmh;
  private $maxTimestamp;
  private $histogram;
  private $startTimestamp;
  private $endTimestamp;

  function __construct($avgKmh, $maxKmh, $maxTimestamp, $histogram, $startTimestamp,
      $endTimestamp) {
    $this->avgKmh = $avgKmh;
    $this->maxKmh = $maxKmh;
    $this->maxTimestamp = $maxTimestamp;
    $this->histogram = $histogram;
    $this->startTimestamp = $startTimestamp;
    $this->endTimestamp = $endTimestamp;
  }

  public function getAvgKmh() { return $this->avgKmh; }
  public function getMaxKmh() { return $this->maxKmh; }
  public function getMaxTimestamp() { return $this->maxTimestamp; }
  public function getHistogram() { return $this->histogram; }
  public function getStartTimestamp() { return $this->startTimestamp; }
  public function getEndTimestamp() { return $this->endTimestamp; }

  public function toString() {
    return 'avgKmh='.$this->avgKmh.', maxKmh='.$this->maxKmh.', maxTimestamp='.$this->maxTimestamp
        .', startTimestamp='.$this->startTimestamp.', endTimestamp='.$this->endTimestamp;
  }
}
?>