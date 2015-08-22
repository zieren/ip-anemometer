import RPi.GPIO as GPIO  #@UnresolvedImport
import datetime
import operator
import threading
import sys
import time

import K


def client_md5():
  return sys.argv[1] if len(sys.argv) > 1 else K.NOT_AVAILABLE


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
  """Convert duration (in millis) to rotations per second."""
  return 1000.0 / duration


def read_stable(pin, num, interval_millis, log = None):
  # Store results in a list to investigate whether this works.
  reads = {}
  for _ in range(num):
    if reads:
      time.sleep(interval_millis / 1000.0)
    r = GPIO.input(pin)
    reads[r] = reads.get(r, 0) + 1
  result = max(reads.iteritems(), key=operator.itemgetter(1))[0]
  if log:
    log.debug('read_stable: %s -> %d' % (reads, result))
  return result
