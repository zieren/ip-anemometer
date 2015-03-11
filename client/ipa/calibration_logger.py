import Queue
import threading
import traceback

import common
import log
from wind_stats import compute_kmh


_WINDOW_SIZE = 10000  # running average for last 10s


class LoggerWorker(threading.Thread):
  """Listens on a Queue and logs received messages asynchronously."""

  def __init__(self, queue, logger):
    threading.Thread.__init__(self)
    self._queue = queue
    self._log = logger

  def run(self):
    try:
      while True:
        element = self._queue.get()
        if not element:
          return
        ts, duration, v, avg, v_max = element
        message = '%s\t%.3f\t%.3f\t%.3f\t%.3f' % (common.timestamp_to_string(ts),
                                                  duration / 1000.0, v, avg, v_max)
        self._log.info(message)
    except Exception:
      self._log.critical(traceback.format_exc())

class CalibrationLogger:
  """Uses a worker thread to not block the event handling thread calling log()."""

  def __init__(self):
    self._v_max = 0
    self._v_window = {}
    self._queue = Queue.Queue()
    self._worker = LoggerWorker(self._queue, log.get_logger('wind_calibrate'))
    self._worker.start()

  def log(self, timestamp_and_duration):
    '''timestamp_and_duration is a tuple of timestamp and duration, both in millis.'''
    ts, duration = timestamp_and_duration
    v = compute_kmh(duration)
    self._v_max = max(self._v_max, v)
    avg = self.update_average(v, ts)
    self._queue.put((ts, duration, v, avg, self._v_max))

  def update_average(self, v, now):
    self._v_window[now] = v
    avg = 0.0
    start = now - _WINDOW_SIZE
    previous_ts = start
    for ts in sorted(self._v_window):
      if ts < start:
        del self._v_window[ts]
        continue
      v = self._v_window[ts]
      avg += (ts - previous_ts) * v
      previous_ts = ts
    avg /= now - start
    return avg

  def terminate(self):
    self._queue.put(None)  # terminate worker
