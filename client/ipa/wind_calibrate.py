#!/usr/bin/env python

import RPi.GPIO as GPIO  #@UnresolvedImport
import subprocess
import traceback

import common
from config import C
import log
from wind import Wind


if __name__ == "__main__":
  logger = log.get_logger('wind_calibrate')
  try:
    logger.info('--- wind_calibrate started, waiting for ntpd ---')
    if subprocess.call('../await_clock_sync.sh'):
      logger.critical('--- failed to sync clock ---')
    else:
      logger.info('--- clock in sync ---')

    GPIO.setmode(GPIO.BOARD)
    wind = Wind(calibration_mode=True)
    raw_input('Press ENTER to quit...\n')  # just in case there's a kb and a screen
    GPIO.cleanup()
    wind.terminate_calibration()
    threads_left = common.join_all_threads(C.TIMEOUT_SHUTDOWN_SECONDS())
    print '--- exiting - threads left: %d ---' % threads_left
  except Exception:
    logger.critical(traceback.format_exc())