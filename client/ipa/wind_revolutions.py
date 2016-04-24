import common
import threading


class Revolutions:
  """Stores the timestamps of wind sensor revolutions."""

  def __init__(self, edges_per_rev):
    self._edges_per_rev = edges_per_rev
    self._lock = threading.RLock()  # re-entrant for use in calibration_add_edge_and_log
    # Edges seen in the current revolution.
    self._edges = 0
    # Timestamps of completed revolutions (epoch seconds).
    self._revs = []

  def get_and_reset(self):
    """Return the timestamps (in milliseconds) of all completed revolutions since the last call."""
    with self._lock:
      revs = self._revs
      self._revs = []
    return revs

  def add_edge(self, pin_ignored):
    """Count one edge."""
    with self._lock:
      self._edges = (self._edges + 1) % self._edges_per_rev
      if self._edges == 0:
        self._revs.append(common.timestamp())

  # ---------- Calibration methods ----------

  def calibration_init(self, log):
    """log is a CalibrationLogger instance."""
    self._log = log
    self._revs.append(0)

  def calibration_add_edge_and_log(self, pin_ignored):
    """Like add_edge(), but also log, on each full revolution, the current time and the revolution
    duration. This allows calibration by comparing with a timestamped reference."""
    with self._lock:
      self.add_edge(pin_ignored)
      if self._edges == 0:
        self._log.log((self._revs[-1], self._revs[-1] - self._revs[-2]))
        self._revs = [self._revs[-1]]
