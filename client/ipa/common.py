import datetime
import threading
import time


def join_all_threads(timeout_seconds):
  """Join all threads using the specified total timeout. Returns the number of threads failed to
  join (0 on success)."""

  timeout_time = time.time() + timeout_seconds
  for t in threading.enumerate():
    if t == threading.current_thread():
      continue
    t.join(timeout_time - time.time())  # negative values are OK
  return len(threading.enumerate()) - 1  # subtract current thread

def timestamp():
  """Return the current timestamp in milliseconds as a long."""
  return long(time.time() * 1000.0)

def timestamp_to_string(timestamp):
  """Return the timestamp as HH:MM:SS.mmm"""
  return '%s.%03d' % (datetime.datetime.fromtimestamp(timestamp / 1000.0).strftime('%H:%M:%S'),
                      timestamp % 1000)  # millisecond part of time

def duration_to_rps(duration):
  return 1000.0 / duration