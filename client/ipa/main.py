#!/usr/bin/env python

import Queue
import sys
import threading
import traceback

import C
import common
import huawei_status
import K
import log
import metadata
import temperature
import uploader
import wind


class Anemometer:
  """Starts threads, processes the main command queue, and shuts down threads."""

  def __init__(self):
    self._log = log.get_logger('ipa.main')
    self._log.info(K.CLIENT_GREETING)
    self._log.info('client md5: %s' % common.client_md5())
    # Create main command queue.
    self._main_cq = Queue.Queue()
    # Create thread termination Event. All threads listen on this whenever they wait, and exit when
    # it's set.
    self._termination_event = threading.Event()
    # Create upload thread (started in run() below).
    self._uploader = uploader.Uploader(self._main_cq, self._termination_event)
    # Create data sources.
    self._wind = wind.Wind()
    self._uploader.add_data_source(self._wind, True)
    self._uploader.add_data_source(temperature.Temperature(), True)
    self._uploader.add_data_source(metadata.Metadata(), False)
    # TODO: This should be optional (auto-detect?).
    self._uploader.add_data_source(huawei_status.HuaweiStatus(), True)

  def _shutdown(self):
    """Attempt to shut down all threads gracefully."""
    # Terminate the uploader:
    self._termination_event.set()
    # Cleanup GPIO:
    self._wind.terminate()
    threads_left = common.join_all_threads(C.TIMEOUT_SHUTDOWN_SECONDS())
    if threads_left:
      self._log.warning('%d thread(s) failed to shutdown' % threads_left)
    else:
      self._log.info('all threads shutdown successfully')

  def _process_commands(self):
    """Listen on the command queue and processes commands."""
    while True:
      command = self._main_cq.get()
      self._log.info('processing command: "%s": "%s"' % command)
      if command[0] == 'exit':
        self._shutdown()
        sys.exit(int(command[1]))
      else:
        self._log.warning('unknown command - ignored: "%s" : "%s"' % command)

  def run(self):
    try:
      self._uploader.start()
      self._process_commands()
    except Exception:
      self._log.critical(traceback.format_exc())


if __name__ == "__main__":
  Anemometer().run()
