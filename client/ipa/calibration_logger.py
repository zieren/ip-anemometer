import Queue
import threading

import common
import K


class LoggerWorker(threading.Thread):
  """Listens on a Queue and logs received messages asynchronously."""

  def __init__(self, queue, log):
    threading.Thread.__init__(self)
    self._queue = queue
    self._log = log

  def run(self):
    while True:
      timestamp_and_duration = self._queue.get()
      if timestamp_and_duration == None:
        return
      timestamp = timestamp_and_duration[0]
      rps = common.duration_to_rps(timestamp_and_duration[1])
      # \t for transfer to spreadsheet
      message = '%s\t%.3f' % (common.timestamp_to_string(timestamp), rps)
      self._log.info(message)


class CalibrationLogger:
  """Uses a worker thread to not block the event handling thread calling log()."""

  def __init__(self):
    log = K.get_logger(K.LOG_NAME_CALIBRATION, K.LOG_FORMAT_CALIBRATION)
    self._queue = Queue.Queue()
    self._worker = LoggerWorker(self._queue, log)
    self._worker.start()

  def log(self, timestamp_and_duration):
    '''timestamp_and_duration is a tuple of timestamp and duration, both in millis.'''
    self._queue.put(timestamp_and_duration)

  def terminate(self):
    self._queue.put(None)  # terminate worker
