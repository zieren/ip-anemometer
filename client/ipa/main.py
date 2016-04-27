#!/usr/bin/env python

# Since some modules may throw on import (e.g. missing dependencies for optional hardware),
# parse arguments first so they are available and --help is processed.
from args import ARGS
ARGS.parse()

import os
import Queue
import RPi.GPIO as GPIO  #@UnresolvedImport
import sys
import threading
import traceback

import common
from config import C
import K
from logger import LOGGER_FACTORY
import metadata
import temperature
import uploader


class Anemometer:
  """Starts threads, processes the main command queue, and shuts down threads."""

  def __init__(self):
    self._log = LOGGER_FACTORY.get_logger('ipa.main')
    self._log.info(K.CLIENT_GREETING)
    auth_info = ((' (authenticated as user "%s")' % ARGS.server_username())
                 if ARGS.server_username() else '')
    self._log.info('URL: %s%s' % (ARGS.server_url(), auth_info))
    self._log.info('MD5: %s' % ARGS.archive_md5())
    if C.DEMO_MODE_ENABLED():
      self._log.warn('DEMO MODE ENABLED')
    # Create main command queue.
    self._main_cq = Queue.Queue()
    # Create thread termination Event for Uploader.
    self._uploader_termination_event = threading.Event()

  def _initialize(self):
    # Create state directory.
    if not os.path.exists(K.STATE_DIR):
      os.mkdir(K.STATE_DIR)
    # Create upload thread (started in run() below).
    self._uploader = uploader.Uploader(self._main_cq, self._uploader_termination_event)
    # Create data sources.
    GPIO.setmode(GPIO.BCM)  # required before Wind()
    self._uploader.add_data_source(temperature.Temperature(), True)
    self._uploader.add_data_source(metadata.Metadata(), False)

    # Import modules only when enabled, since some again import optional libraries (e.g. DHT).
    # In demo mode, enable all and import demo instances.
    if True:  # TODO: Make wind optional.
      if C.DEMO_MODE_ENABLED():
        import demo.demo_wind as wind  # @UnusedImport
      else:
        import wind  # @Reimport
      self._uploader.add_data_source(wind.Wind(), True)
    if C.DHT_ENABLED() or C.DEMO_MODE_ENABLED():
      if C.DEMO_MODE_ENABLED():
        import demo.demo_dht as dht  # @UnusedImport
      else:
        import dht  # @Reimport
      self._uploader.add_data_source(dht.Dht(), True)
    if C.ADC_ENABLED() or C.DEMO_MODE_ENABLED():
      if C.DEMO_MODE_ENABLED():
        import demo.demo_spi_adc as spi_adc  # @UnusedImport
      else:
        import spi_adc  # @Reimport
      self._uploader.add_data_source(spi_adc.SpiAdc(), True)
    if C.HUAWEI_ENABLED() or C.DEMO_MODE_ENABLED():
      if C.DEMO_MODE_ENABLED():
        import demo.demo_huawei_status as huawei_status  # @UnusedImport
      else:
        import huawei_status  # @Reimport
      self._uploader.add_data_source(huawei_status.HuaweiStatus(), True)
    if C.DOOR_ENABLED() or C.DEMO_MODE_ENABLED():
      if C.DEMO_MODE_ENABLED():
        import demo.demo_door as door  # @UnusedImport
      else:
        import door  # @Reimport
      self._uploader.add_data_source(door.Door(), True)
    if C.PILOTS_ENABLED() or C.DEMO_MODE_ENABLED():
      if C.DEMO_MODE_ENABLED():
        import demo.demo_pilot_count as pilot_count  # @UnusedImport
      else:
        import pilot_count  # @Reimport
      self._uploader.add_data_source(pilot_count.PilotCount(), True)

  def _shutdown(self):
    """Deregister GPIO callbacks and attempt to shut down all threads gracefully."""
    self._uploader_termination_event.set()
    threads_left = common.join_all_threads(C.TIMEOUT_SHUTDOWN_SECONDS())
    if threads_left:
      self._log.warning('%d thread(s) failed to shutdown' % threads_left)
    else:
      self._log.info('all threads shutdown successfully')
    if not C.DEMO_MODE_ENABLED():
      GPIO.cleanup()  # Do this last, otherwise GPIO may fail in running threads.

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
      self._initialize()
      self._uploader.start()
      self._process_commands()
    except Exception:
      self._log.critical(traceback.format_exc())


if __name__ == "__main__":
  Anemometer().run()
