#!/usr/bin/env python

import RPi.GPIO as GPIO  #@UnresolvedImport
import subprocess
import traceback

import common
from config import C
from logger import LOGGER_FACTORY
from wind import Wind


if __name__ == "__main__":
  log = LOGGER_FACTORY.get_logger('wind_calibrate')
  try:
    log.info('--- wind_calibrate started, waiting for ntpd ---')
    if subprocess.call('../await_clock_sync.sh'):
      log.critical('--- failed to sync clock ---')
    else:
      log.info('--- clock in sync ---')

    GPIO.setmode(GPIO.BCM)
    wind = Wind(calibration_mode=True)
    raw_input('Press ENTER to quit...\n')  # just in case there's a kb and a screen
    GPIO.cleanup()
    wind.terminate_calibration()
    threads_left = common.join_all_threads(C.TIMEOUT_SHUTDOWN_SECONDS())
    print '--- exiting - threads left: %d ---' % threads_left
  except Exception:
    log.critical(traceback.format_exc())