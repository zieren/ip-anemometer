import threading
import time

import C


class Stats:
  # Remember config value to avoid locking call in event handler.
  _EDGES = C.WIND_EDGES_PER_REV()

  def __init__(self):
    self._lock = threading.RLock()  # re-entrant for use in calibration_add_edge_and_log
    # Edges seen in the current revolution.
    self._edges = 0
    # Timestamps of completed revolutions.
    self._revs = []

  def get_and_reset(self):
    """Returns the timestamps (in milliseconds) of all completed revolutions since the last call."""
    with self._lock:
      revs = self._revs
      self._revs = []
    # Round to milliseconds.
    return [int(1000 * t + 0.5) for t in revs]

  def add_edge(self, pin_ignored):
    """Counts one edge."""
    with self._lock:
      self._edges = (self._edges + 1) % Stats._EDGES
      if self._edges == 0:
        self._revs.append(time.time())

  # ---------- Calibration methods ----------

  def calibration_init(self, log):
    """log is a CalibrationLogger instance."""
    self._log = log
    self._revs.append(0)

  def calibration_add_edge_and_log(self, pin_ignored):
    """
    Like add_edge(), but also logs, on each full revolution, the current time and the revolution
    duration. This allows calibration by comparing with a reference recording.
    """
    with self._lock:
      self.add_edge(pin_ignored)
      if self._edges == 0:
        self._log.log((self._revs[-1], self._revs[-1] - self._revs[-2]))
        self._revs = [self._revs[-1]]
