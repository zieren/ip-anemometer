#!/usr/bin/env python

# --- WIND SENSOR CALIBRATION ---
#
# How to calibrate the wind sensor:
# 1. Make sure that network is available (e.g. 3G stick) so ntpd can sync the clock.
# 2. Go somewhere with a wide range of wind speeds (may require multiple days/attempts).
# 3. Start this module, e.g. via cron @reboot if you won't have a screen/keyboard in the field.
#    Remember that it needs to run as root for GPIO access.
# 4. Place a calibrated anemometer next to the sensor so it measures the same wind speed.
# 5. Record the calibrated anemometer's reading on video. Be sure to timestamp the video, e.g. by
#    holding an atomic (or just reasonably accurate) clock in the image.
# 6. Wait until you've seen all the wind speeds you can expect at the moment. The Pi will log
#    timestamp and rotations per second.
# 7. Import the log output into a spreadsheed.
# 8. Manually enter the reference readouts from the video. It's useful to create a new column with
#    the corresponding time index in the video, using the recorded timestamp.
# 9. Scatterplot the reference wind speed as a function of rotations per second.
# 10. Fit a curve to the data. I used b0/(1+x)+b1*x, where x is the rotations per second, and bo and
#     b1 are chosen to minimize the sum of squares. See e.g. http://youtu.be/PiBYFUOP-GI for doing
#     this in OpenOffice. The reason for the nonlinear formula is that the sensor has a certain
#     friction, so it will start rotating only when the wind speed exceeds about 2 km/h. For high
#     speeds the function is approximately linear, while for low speeds it has a constant component.
#
# TODO: Complete these instructions once there's a place to put the formula and parameters.

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
    wind.terminateCalibration()
    threads_left = common.join_all_threads(C.TIMEOUT_SHUTDOWN_SECONDS())
    print '--- exiting - threads left: %d ---' % threads_left
  except Exception:
    logger.critical(traceback.format_exc())