# XXX make sure all is float!

_NO_WIND_DURATION = 10 * 1000 # Longer rotations are considered 0 km/h.


class Wind:
  (AVG_KMH, MAX_KMH, MAX_TIMESTAMP, HISTOGRAM, START_TIMESTAMP, END_TIMESTAMP) = range(6)


class WindStatsCalculator:
  """Calculates wind stats from a sequence of timestamps representing revolutions, and a start/end
  timestamp pair. This should match WindStatsCalculator on the server."""

  class Phase:
    BEFORE_FIRST_TIMESTAMP, BEFORE_FIRST_KMH, STEADY_STATE = range(3)

  def __init__(self, start_timestamp):
    self._phase = WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP
    self._previous_timestamp = 0  # will be initialized in the first call
    self._start_timestamp = start_timestamp
    self._reset()

  def _reset(self):
    self._max_kmh = 0
    self._max_timestamp = 0
    self._avg_kmh = 0
    self._histogram = {}

  def next_timestamp(self, timestamp):
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP:
      self._previous_timestamp = timestamp
      self._phase = WindStatsCalculator.Phase.BEFORE_FIRST_KMH
      return

    # Compute duration (i.e. weight) and speed.
    duration = timestamp - self._previous_timestamp
    kmh = compute_kmh(duration)
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_KMH:
      start_silence_duration = self._previous_timestamp - self._start_timestamp
      # Extrapolation using kmh, or less if the start silence is longer.
      start_kmh = min(kmh, compute_kmh(start_silence_duration))
      self._update(start_kmh, start_silence_duration, timestamp)
      self._phase = WindStatsCalculator.Phase.STEADY_STATE

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
    """Return a WindStats object and reset all internal state prior to the time it covers."""
    if self._phase == WindStatsCalculator.Phase.BEFORE_FIRST_TIMESTAMP:
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
    stats = {Wind.AVG_KMH: self._avg_kmh,
             Wind.MAX_KMH: self._max_kmh,
             Wind.MAX_TIMESTAMP: self._max_timestamp,
             Wind.HISTOGRAM: self._histogram,
             Wind.START_TIMESTAMP: self._start_timestamp,
             Wind.END_TIMESTAMP: end_timestamp}
    self._start_timestamp = end_timestamp  # start next window
    self._reset()
    return stats


def compute_kmh(duration):
  """Convert revolution duration to windspeed in km/h."""
  if duration >= _NO_WIND_DURATION:
    return 0.0
  # TODO: Extract magic values.
  rps = 1000.0 / duration  # rotations per second
  return (1.761 / (1.0 + rps) + 3.013 * rps)
