# TODO: Unique and proper formatting (mostly newlines) throughout.
# TODO: There are several instances where we're at risk of flooding logs with repeated messages.
# Maybe wrap loggers and output repetition counts for duplicates? Though that would only work for
# immediate duplicates.

import Queue
import sys
import threading

import C
import common
import K
import metadata
import temperature
import uploader
import wind


class Anemometer:
  """Starts threads, processes the main command queue, and shuts down threads."""

  def __init__(self):
    self._log = K.get_logger(K.LOG_NAME_MAIN)
    self._log.info(K.CLIENT_GREETING)
    # Create main command queue.
    self._main_cq = Queue.Queue()
    # Create thread termination Event. All threads listen on this whenever they wait, and exit when
    # it's set.
    self._termination_event = threading.Event()
    # Create upload thread (started in run() below).
    self._uploader = uploader.Uploader(self._main_cq, self._termination_event)
    # Create data sources.
    self._wind = wind.Wind(wind.Wind.MODE_PRECISION)  # TODO: Implement mode switch in cfg.
    self._uploader.add_data_source(self._wind, True)
    self._uploader.add_data_source(temperature.Temperature(), True)
    self._uploader.add_data_source(metadata.Metadata(), False)


  def _shutdown(self):
    """Attempts to shut down all threads gracefully."""

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
    """Listens on the command queue and processes commands."""

    while True:
      command = self._main_cq.get()
      # TODO: This (and other "processing command" events) will easily clutter the log.
      self._log.info('processing command: "%s": "%s"' % command)
      if command[0] == K.COMMAND_RESTART:
        self._shutdown()
        sys.exit(0)  # restarting is the default on successful termination
      elif command[0] == K.COMMAND_SHUTDOWN:
        self._shutdown()
        sys.exit(K.RETURN_VALUE_SHUTDOWN)
      elif command[0] == K.COMMAND_REBOOT:
        self._shutdown()
        sys.exit(K.RETURN_VALUE_REBOOT)
      else:
        # TODO: Avoid flooding logs with this?
        self._log.warning('unknown command - ignored: "%s" : "%s"' % command)


  def run(self):
    self._uploader.start()
    self._process_commands()


if __name__ == "__main__":
  Anemometer().run()
