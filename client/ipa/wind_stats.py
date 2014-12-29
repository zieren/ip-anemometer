import common


_NO_WIND_DURATION = 10000L  # Revolutions longer than 10 seconds are considered 0 km/h.


class WindKey:
  """Keys in the stats dict."""
  (AVG_KMH, MAX_KMH, MAX_TIMESTAMP, HISTOGRAM, START_TIMESTAMP, END_TIMESTAMP) = range(6)
  # NOTE: Keep these in sync with common.php and ipa.js.
  # TODO: Should these be string literals for easier access on the server?


class WindStatsCalculator:
  """Calculates wind stats from a sequence of timestamps representing revolutions. This should match
  WindStatsCalculator on the server."""

  # States we move through strictly forward.
  _BEFORE_FIRST_TIMESTAMP, _BEFORE_FIRST_KMH, _STEADY_STATE = range(3)

  def __init__(self, start_timestamp):
    """start_timestamp: Startup of the wind sensor."""
    self._phase = WindStatsCalculator._BEFORE_FIRST_TIMESTAMP
    self._previous_timestamp = 0  # will be initialized in the first call
    # TODO: Make sure end timestamps are considered exclusive throughout.
    self._start_timestamp = start_timestamp
    self._reset()

  def _reset(self):
    self._max_kmh = 0.0
    self._max_timestamp = 0
    self._avg_kmh = 0.0
    self._histogram = {}

  def next_timestamp(self, timestamp):
    """Consider the next timestamp for statistics."""
    if self._phase == WindStatsCalculator._BEFORE_FIRST_TIMESTAMP:
      self._previous_timestamp = timestamp
      self._phase = WindStatsCalculator._BEFORE_FIRST_KMH
      return

    # Compute duration (i.e. weight) and speed.
    duration = timestamp - self._previous_timestamp
    if duration <= 0:  # maybe ntpd adjusted the clock?
      return
    kmh = compute_kmh(duration)
    if self._phase == WindStatsCalculator._BEFORE_FIRST_KMH:
      start_silence_duration = self._previous_timestamp - self._start_timestamp
      if start_silence_duration > 0:
        # Extrapolation using kmh, or less if the start silence is longer.
        start_kmh = min(kmh, compute_kmh(start_silence_duration))
        self._update(start_kmh, start_silence_duration, timestamp)
      self._phase = WindStatsCalculator._STEADY_STATE

    self._update(kmh, duration, timestamp)
    # Remember timestamp for next call.
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
    end_timestamp (padding with zero km/h). The end timestamp is returned in the result. """

    if self._phase != WindStatsCalculator._STEADY_STATE:
      if end_timestamp - self._start_timestamp < _NO_WIND_DURATION:
        # We can still have a nonzero speed for this interval so we can't return anything.
        return None


    if self._phase == WindStatsCalculator._BEFORE_FIRST_TIMESTAMP:
      if end_timestamp - self._start_timestamp < _NO_WIND_DURATION:
        # We can still have a nonzero speed for this interval so we can't return anything.
        return None
      # We have exceeded _NO_WIND_DURATION so we can safely set the previous revolution's timestamp
      # to be the start timestamp. We will insert a "virtual" timestamp below.
      self._previous_timestamp = self._start_timestamp

    end_silence_duration = end_timestamp - self._previous_timestamp
    if end_silence_duration > _NO_WIND_DURATION:
      # Generate a "virtual" timestamp that is _NO_WIND_DURATION before the end, so that the next
      # timestamp will produce 0 km/h. Consider the time up to the virtual timestamp as 0 km/h.
      self._update(0.0, end_silence_duration - _NO_WIND_DURATION, self._previous_timestamp)
      self._previous_timestamp = end_timestamp - _NO_WIND_DURATION  # "virtual" timestamp
    end_timestamp = self._previous_timestamp
    total_duration = end_timestamp - self._start_timestamp
    # If we are called in intervals smaller than _NO_WIND_DURATION and no revolution has occured,
    # self._previous_timestamp was the last sample's end timestamp and is now self._start_timestamp,
    # resulting in a total_duration of 0. Don't return anything, for the same reason as above.
    if total_duration == 0:
      return None

    # end_timestamp - self._previous_timestamp <= _NO_WIND_DURATION
    # self._previous_timestamp > self._start_timestamp

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
  rps = common.duration_to_rps(duration)  # rotations per second
  return (1.761 / (1.0 + rps) + 3.013 * rps)
