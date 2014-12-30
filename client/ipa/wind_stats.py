import C
import common


# Remember config values to avoid locking call.
_HSF = C.WIND_HSF()
_LSF = C.WIND_LSF()
_MAX_ROTATION = C.WIND_MAX_ROTATION()


class WindKey:
  """Keys in the stats dict."""
  (AVG_KMH, MAX_KMH, MAX_TIMESTAMP, HISTOGRAM, START_TIMESTAMP, END_TIMESTAMP) = range(6)
  # NOTE: Keep these in sync with common.php and ipa.js.
  # TODO: Should these be string literals for easier access on the server?


class WindStatsCalculator:
  """Calculates wind stats from a sequence of timestamps representing revolutions."""

  def __init__(self, start_timestamp):
    """start_timestamp: Startup of the wind sensor."""
    self._start_timestamp = start_timestamp
    self._previous_timestamp = start_timestamp
    self._had_first_timestamp = False
    self._reset()

  def _reset(self):
    self._max_kmh = 0.0
    self._max_timestamp = 0
    self._avg_kmh = 0.0
    self._histogram = {}

  def next_timestamp(self, timestamp):
    """Consider the next timestamp for statistics."""
    # Compute duration (i.e. weight) and speed.
    duration = timestamp - self._previous_timestamp
    if duration <= 0:  # maybe ntpd adjusted the clock?
      return
    kmh = compute_kmh(duration) if self._had_first_timestamp else 0.0
    self._had_first_timestamp = True
    self._update(kmh, duration, timestamp)
    self._previous_timestamp = timestamp

  def _update(self, kmh, duration, timestamp):
    # Update histogram.
    int_kmh = int(kmh)
    self._histogram[int_kmh] = self._histogram.setdefault(int_kmh, 0.0) + duration
    # Update maximum.
    if kmh >= self._max_kmh:
      self._max_kmh = kmh
      # If the gap between timestamps t_1 and t_2 exceeds _MAX_ROTATION we'll record the resulting
      # 0 km/h with t_2 even though t_2 may be an arbitrary amount of time after t_1 and the 0 km/h
      # measurement is valid for ]t_1, t_2]. So the timestamp may be "delayed" for the 0 km/h case.
      # But we only need it for self._max_timestamp so this is not an issue. In fact we _want_ the
      # newest timestamp for the maximum.
      self._max_timestamp = timestamp
    # Update average.
    self._avg_kmh += kmh * duration

  def get_stats_and_reset(self, end_timestamp):
    """Return statistics in a dict keyed by WindKey. Statistics begin at the start_timestamp for the
    first call, and at the end timestamp returned in the previous call for all subsequent calls.
    They end at the last revolution timestamp before end_timestamp. If no revolutions occured,
    padding with 0 km/h is done to cover at least up to (end_timestamp - 2 * _MAX_ROTATION
    + 1). The end timestamp is returned in the result."""

    # self._previous_timestamp needs to advance even if no revolutions occur. So we add "virtual"
    # timestamps that yield 0 km/h.
    if end_timestamp - self._previous_timestamp >= 2 * _MAX_ROTATION:
      self.next_timestamp(end_timestamp - _MAX_ROTATION)

    end_timestamp = self._previous_timestamp
    total_duration = end_timestamp - self._start_timestamp
    if total_duration == 0:  # No new timestamp (regular or virtual) since creation or last call.
      return None

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
  if duration >= _MAX_ROTATION:
    return 0.0
  rps = common.duration_to_rps(duration)  # rotations per second
  return (_LSF / (1.0 + rps) + _HSF * rps)
