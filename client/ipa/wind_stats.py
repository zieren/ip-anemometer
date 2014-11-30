_NO_WIND_DURATION = float(10 * 1000)  # Longer rotations are considered 0 km/h.


class WindKey:
  """Keys in the stats dict."""
  (AVG_KMH, MAX_KMH, MAX_TIMESTAMP, HISTOGRAM, START_TIMESTAMP, END_TIMESTAMP) = range(6)
  # TODO: Should these be string literals for easier access on the server?
  # TODO: Timestamps should probably be integers in stats result. Make sure serialization doesn't
  # crop anything.


class WindStatsCalculator:
  """Calculates wind stats from a sequence of timestamps representing revolutions. This should match
  WindStatsCalculator on the server."""

  _BEFORE_FIRST_TIMESTAMP, _BEFORE_FIRST_KMH, _STEADY_STATE = range(3)

  def __init__(self, start_timestamp):
    """start_timestamp: Startup of the wind sensor."""
    self._phase = WindStatsCalculator._BEFORE_FIRST_TIMESTAMP
    self._previous_timestamp = 0.0  # will be initialized in the first call
    self._start_timestamp = float(start_timestamp)
    self._reset()

  def _reset(self):
    self._max_kmh = 0.0
    self._max_timestamp = 0.0
    self._avg_kmh = 0.0
    self._histogram = {}

  def next_timestamp(self, timestamp):
    """Consider the next timestamp for statistics."""
    timestamp = float(timestamp)
    if self._phase == WindStatsCalculator._BEFORE_FIRST_TIMESTAMP:
      self._previous_timestamp = timestamp
      self._phase = WindStatsCalculator._BEFORE_FIRST_KMH
      return

    # Compute duration (i.e. weight) and speed.
    duration = timestamp - self._previous_timestamp
    kmh = compute_kmh(duration)
    if self._phase == WindStatsCalculator._BEFORE_FIRST_KMH:
      start_silence_duration = self._previous_timestamp - self._start_timestamp
      # Extrapolation using kmh, or less if the start silence is longer.
      start_kmh = min(kmh, compute_kmh(start_silence_duration))
      self._update(start_kmh, start_silence_duration, timestamp)
      self._phase = WindStatsCalculator._STEADY_STATE

    self._update(kmh, duration, timestamp)
    # Remember timestamp for next row.
    self._previous_timestamp = timestamp

  def _update(self, kmh, duration, timestamp):
    # Update histogram.
    int_kmh = int(kmh)
    self._histogram[int_kmh] = self._histogram.setdefault(int_kmh, 0.0) + duration
    # Update maximum.
    if kmh > self._max_kmh:
      self._max_kmh = kmh
      self._max_timestamp = timestamp
    # Update average.
    self._avg_kmh += kmh * duration

  def get_stats_and_reset(self, end_timestamp):
    """Return statistics in a dict keyed by WindKey. Statistics begin at the start_timestamp for the
    first call, and at the end timestamp of the previous call for all subsequent calls. They end at
    the last revolution timestamp before end_timestamp, but never more than _NO_WIND_DURATION before
    end_timestamp (padding with zero km/h). The actual end timestamp is returned in the result. """
    end_timestamp = float(end_timestamp)
    if self._phase == WindStatsCalculator._BEFORE_FIRST_TIMESTAMP:
      # didn't initialize self._previous_timestamp yet
      self._previous_timestamp = self._start_timestamp

    end_silence_duration = end_timestamp - self._previous_timestamp
    if end_silence_duration >= _NO_WIND_DURATION:
      # Generate a "virtual" timestamp that is _NO_WIND_DURATION before the end, so that the next
      # timestamp is guaranteed to produce 0 km/h. Consider the time up to the virtual timestamp as
      # 0 km/h.
      self._update(0.0, end_silence_duration - _NO_WIND_DURATION, self._previous_timestamp)
      self._previous_timestamp = end_timestamp - _NO_WIND_DURATION  # "virtual" timestamp
    end_timestamp = self._previous_timestamp
    total_duration = end_timestamp - self._start_timestamp
    # Compute average.
    self._avg_kmh /= total_duration
    # Convert histogram to relative values.
    for k, v in self._histogram.iteritems():
      self._histogram[k] = v / total_duration
    stats = {WindKey.AVG_KMH: self._avg_kmh,
             WindKey.MAX_KMH: self._max_kmh,
             WindKey.MAX_TIMESTAMP: self._max_timestamp,
             WindKey.HISTOGRAM: self._histogram,
             WindKey.START_TIMESTAMP: self._start_timestamp,
             WindKey.END_TIMESTAMP: end_timestamp}
    self._start_timestamp = end_timestamp  # start next window
    self._reset()
    return stats


def compute_kmh(duration):
  """Convert revolution duration to windspeed in km/h."""
  if duration >= _NO_WIND_DURATION:
    return 0.0
  # TODO: Extract magic values (including _NO_WIND_DURATION).
  rps = 1000.0 / duration  # rotations per second
  return (1.761 / (1.0 + rps) + 3.013 * rps)
