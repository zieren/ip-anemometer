import common


class WindStats:
  """Calculates wind stats from a sequence of timestamps representing revolutions."""

  def __init__(self, hsf, lsf, max_rotation, start_timestamp):
    """start_timestamp: Startup of the wind sensor."""
    self._hsf = hsf
    self._lsf = lsf
    self._max_rotation = max_rotation
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
    kmh = self.compute_kmh(duration) if self._had_first_timestamp else 0.0
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
      # If the gap between timestamps t_1 and t_2 exceeds max_rotation we'll record the resulting
      # 0 km/h with t_2 even though t_2 may be an arbitrary amount of time after t_1 and the 0 km/h
      # measurement is valid for ]t_1, t_2]. So the timestamp may be "delayed" for the 0 km/h case.
      # But we only need it for self._max_timestamp so this is not an issue. In fact we _want_ the
      # newest timestamp for the maximum.
      self._max_timestamp = timestamp
    # Update average.
    self._avg_kmh += kmh * duration

  def get_stats_and_reset(self, end_timestamp):
    """Return statistics dict. Statistics begin at the start_timestamp for the first call, and at
    the end timestamp returned in the previous call for all subsequent calls. They end at the last
    revolution timestamp before end_timestamp. If no revolutions occured, padding with 0 km/h is
    done to cover at least up to (end_timestamp - 2 * max_rotation + 1). The end timestamp is
    returned in the result.

    If no stats are available yet (e.g. two calls in "rapid" succession), None is returned."""

    # self._previous_timestamp needs to advance even if no revolutions occur. So we add "virtual"
    # timestamps that yield 0 km/h.
    if end_timestamp - self._previous_timestamp >= 2 * self._max_rotation:
      self.next_timestamp(end_timestamp - self._max_rotation)

    end_timestamp = self._previous_timestamp
    total_duration = end_timestamp - self._start_timestamp
    if total_duration == 0:  # No new timestamp (regular or virtual) since creation or last call.
      return None

    # Compute average.
    self._avg_kmh /= total_duration
    # Convert histogram to relative values.
    for k, v in self._histogram.iteritems():
      self._histogram[k] = v / total_duration
    stats = {'avg': self._avg_kmh,
             'max': self._max_kmh,
             'max_ts': self._max_timestamp,
             'hist': self._histogram,
             'start_ts': self._start_timestamp,
             'end_ts': end_timestamp}
    self._start_timestamp = end_timestamp  # start next window
    self._reset()
    return stats

  def compute_kmh(self, duration):
    """Convert revolution duration to windspeed in km/h."""
    if duration >= self._max_rotation:
      return 0.0
    rps = common.duration_to_rps(duration)  # rotations per second
    return (self._lsf / (1.0 + rps) + self._hsf * rps)
