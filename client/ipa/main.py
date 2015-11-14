#!/usr/bin/env python

import Queue
import RPi.GPIO as GPIO  #@UnresolvedImport
import os
import sys
import threading
import traceback

import common
from config import C
import dht
import door
import huawei_status
import K
import log
import metadata
import pilot_count
import spi_adc  #@UnresolvedImport
import temperature
import uploader
if C.DEMO_MODE_ENABLED():
  import demo.demo_wind as wind  # @UnusedImport
else:
  import wind  # @Reimport


class Anemometer:
  """Starts threads, processes the main command queue, and shuts down threads."""

  def __init__(self):
    self._log = log.get_logger('ipa.main')
    self._log.info(K.CLIENT_GREETING)
    self._log.info('client md5: %s' % common.client_md5())
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
    GPIO.setmode(GPIO.BOARD)  # required before Wind()
    self._uploader.add_data_source(wind.Wind(), True)
    self._uploader.add_data_source(temperature.Temperature(), True)
    self._uploader.add_data_source(metadata.Metadata(), False)
    if C.DHT_ENABLED():
      self._uploader.add_data_source(dht.Dht(), True)
    if C.ADC_ENABLED():
      self._uploader.add_data_source(spi_adc.SpiAdc(), True)
    if C.HUAWEI_ENABLED():
      self._uploader.add_data_source(huawei_status.HuaweiStatus(), True)
    if C.DOOR_ENABLED():
      self._uploader.add_data_source(door.Door(), True)
    if C.PILOTS_ENABLED():
      self._uploader.add_data_source(pilot_count.PilotCount(), True)

  def _shutdown(self):
    """Deregister GPIO callbacks and attempt to shut down all threads gracefully."""
    GPIO.cleanup()
    self._uploader_termination_event.set()
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
      self._initialize()
      self._uploader.start()
      self._process_commands()
    except Exception:
      self._log.critical(traceback.format_exc())


if __name__ == "__main__":
  Anemometer().run()
