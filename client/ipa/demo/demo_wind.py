import common
import random

class Wind:
  def __init__(self):
    self._last_ts = common.timestamp()
    self._avg = random.random() * 30

  def get_sample(self):
    now_ts = common.timestamp()
    self._avg += random.random() * 6 - 3
    self._avg = min(40, abs(self._avg))
    maxKmh = self._avg + random.random() * self._avg
    mean_ts = (now_ts + self._last_ts) / 2
    wind = {
        'avg': self._avg,
        'max': maxKmh,
        'max_ts': mean_ts,
        'hist': {int(self._avg): 1},
        'start_ts': self._last_ts,
        'end_ts': now_ts}
    self._last_ts = now_ts
    return 'wind', wind
