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
