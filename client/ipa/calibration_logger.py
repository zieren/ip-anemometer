import Queue
import datetime
import threading

import K


class LoggerWorker(threading.Thread):
  """Listens on a Queue and logs received messages asynchronously."""

  def __init__(self, queue, log):
    threading.Thread.__init__(self)
    self._queue = queue
    self._log = log

  def run(self):
    while True:
      time_and_duration = self._queue.get()
      if time_and_duration == None:
        return
      t = time_and_duration[0]
      rps = 1 / time_and_duration[1]
      message = '%s.%03d\t%.3f' % (  # \t for transfer to spreadsheet
          datetime.datetime.fromtimestamp(t).strftime('%H:%M:%S'),
          (t - int(t)) * 1000,  # millisecond part of time
          rps)
      self._log.info(message)


class CalibrationLogger:
  """Uses a worker thread to not block the event handling thread calling log()."""

  def __init__(self):
    log = K.get_logger(K.LOG_NAME_CALIBRATION, K.LOG_FORMAT_CALIBRATION)
    self._queue = Queue.Queue()
    self._worker = LoggerWorker(self._queue, log)
    self._worker.start()

  def log(self, time_and_duration):
    '''time_and_duration is a tuple of timestamp and duration, both in (float) seconds.'''
    self._queue.put(time_and_duration)

  def terminate(self):
    self._queue.put(None)  # terminate worker
