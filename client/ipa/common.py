import datetime
import threading
import time


def join_all_threads(timeout_seconds):
  """Join all (non-daemon) threads using the specified total timeout. Returns the number of threads
  failed to join (i.e. 0 on success)."""
  timeout_time = time.time() + timeout_seconds
  skipped_threads = 0
  for t in threading.enumerate():
    if t == threading.current_thread() or t.daemon:
      skipped_threads += 1
      continue
    t.join(timeout_time - time.time())  # negative values are OK
  return len(threading.enumerate()) - skipped_threads


def timestamp():
  """Return the current timestamp in milliseconds as a long."""
  return long(time.time() * 1000.0)


def timestamp_to_string(timestamp):
  """Return the timestamp as HH:MM:SS.mmm"""
  return '%s.%03d' % (datetime.datetime.fromtimestamp(timestamp / 1000.0).strftime('%H:%M:%S'),
                      timestamp % 1000)  # millisecond part of time


def duration_to_rps(duration):
  """Convert duration (in millis) to rotations per second."""
  return 1000.0 / duration
