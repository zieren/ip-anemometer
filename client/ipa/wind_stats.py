class Wind:
  (AVG_KMH, MAX_KMH, MAX_TIMESTAMP_SECONDS, HISTOGRAM, START_TIMESTAMP_SECONDS,
   END_TIMESTAMP_SECONDS) = range(6)


class WindStatsCalculator:
  """Calculates wind stats from a sequence of timestamps representing revolutions, and a start/end
  timestamp pair. This should match WindStatsCalculator on the server."""

  class Phase:
    BEFORE_FIRST_TIMESTAMP, BEFORE_FIRST_KMH, STEADY_STATE = range(3)

  def __init__(self, startTimestampMillis):
    self._phase = WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP

    self._maxKmh = 0
    self._maxTimestampSeconds = 0
    self._avgKmh = 0
    self._histogram = {}

    self._previousTimestampMillis
    self._previousKmh = 0  # assume 0 if we have no timestamps at all

    self._startTimestampMillis = startTimestampMillis

  def nextTimestamp(self, timestampMillis):
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP:
      self._previousTimestampMillis = timestampMillis
      self._phase = WindStatsCalculator.Phase.BEFORE_FIRST_KMH
      return

    # Compute duration (i.e. weight) and speed.
    durationSeconds = (timestampMillis - self._previousTimestampMillis) / 1000
    kmh = WindStatsCalculator.computeKmh(durationSeconds)
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_KMH:
      startSilenceDurationSeconds = (self._previousTimestampMillis
                                     - self._startTimestampMillis) / 1000
      # Extrapolation using kmh, or less if the start silence is longer.
      startKmh = min(kmh, WindStatsCalculator.computeKmh(startSilenceDurationSeconds))
      self._update(startKmh, startSilenceDurationSeconds, timestampMillis)
      self._phase = WindStatsCalculator.Phase.STEADY_STATE

    self._update(kmh, durationSeconds, timestampMillis)
    # Remember timestamp for next row.
    self._previousTimestampMillis = timestampMillis
    # Remember km/h for extrapolation at the end.
    self._previousKmh = kmh

  def _update(self, kmh, durationSeconds, timestampMillis):
    # Update histogram.
    self._histogram[int(kmh)] += durationSeconds
    # Update maximum.
    if kmh > self._maxKmh:
      self._maxKmh = kmh
      self._maxTimestampSeconds = timestampMillis / 1000
    # Update average.
    self._avgKmh += kmh * durationSeconds

  def finalizeAndGetStats(self):
    """Finalizes computation and returns a WindStats object."""
    if self._phase == WindStatsCalculator.Phase.FINALIZED:
      raise RuntimeError('cannot finalize stats - already finalized')
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP:
      # didn't initialize self._previousTimestamp yet
      self._previousTimestampMillis = self._startTimestampMillis

    endSilenceDurationSeconds = (self._endTimestampMillis
                                 - self._previousTimestampMillis) / 1000
    # Extrapolation using self._previousKmh, or less if the end silence is longer.
    endKmh = min(self._previousKmh, WindStatsCalculator.computeKmh(endSilenceDurationSeconds))
    self._update(endKmh, endSilenceDurationSeconds, self._previousTimestampMillis)
    totalDurationSeconds = (self._endTimestampMillis - self._startTimestampMillis) / 1000
    # Compute average.
    self._avgKmh /= totalDurationSeconds
    # Convert histogram to relative values.
    for k, v in self._histogram.iteritems():
      self._histogram[k] = v / totalDurationSeconds
    ### ksort(self._histogram) probably not needed
    self._phase = WindStatsCalculator.Phase.FINALIZED
    return {Wind.AVG_KMH: self._avgKmh,
            Wind.MAX_KMH: self._maxKmh,
            Wind.MAX_TIMESTAMP_SECONDS: self._maxTimestampSeconds,
            Wind.HISTOGRAM: self._histogram,
            Wind.START_TIMESTAMP_SECONDS: self._startTimestampMillis / 1000,
            Wind.END_TIMESTAMP_SECONDS: self._endTimestampMillis / 1000}


def computeKmh(durationSeconds):
  """Convert revolution duration to windspeed in km/h."""
  # TODO: Extract magic values.
  rps = 1 / durationSeconds  # rotations per second
  return 0 if durationSeconds >= 10 else (1.761 / (1 + rps) + 3.013 * rps)
