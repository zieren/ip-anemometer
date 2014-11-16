import RPi.GPIO as GPIO  #@UnresolvedImport
import time

import C
import calibration_logger
import K
from wind_sensor import Revolutions

# TODO: Implement aggregate mode

class Wind:
  """Provides wind speed. Measurement will start immediately on creation."""

  MODE_PRECISION, MODE_AGGREGATE, MODE_CALIBRATE = range(3)
  _mode_to_string = {
     MODE_PRECISION: 'precision', MODE_AGGREGATE: 'aggregate', MODE_CALIBRATE: 'calibrate'
  }

  def __init__(self, mode):
    self._log = K.get_logger(K.LOG_NAME_WIND)
    self._revolutions = Revolutions()
    self._mode = mode
    if mode == Wind.MODE_PRECISION:
      self._register_callback(self._revolutions.add_edge)
    elif mode == Wind.MODE_AGGREGATE:
      raise RuntimeError('not implemented: %s' % self._mode_to_string[mode])
    elif mode == Wind.MODE_CALIBRATE:
      self._calibration_logger = calibration_logger.CalibrationLogger()
      self._revolutions.calibration_init(self._calibration_logger)
      self._register_callback(self._revolutions.calibration_add_edge_and_log)
    self._startup_time = time.time()
    self._log.info('initialized (mode: %s)' % self._mode_to_string[mode])

  def _register_callback(self, callback):
    # Initialize GPIO.
    GPIO.setmode(GPIO.BOARD)
    GPIO.setup(C.WIND_INPUT_PIN(), GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    # Up to (at least) GPIO 0.5.7 edge detection is partly broken. FALLING and RISING behave like
    # BOTH. I.e. BOTH is the only mode that works as expected, so we use BOTH even though that makes
    # computation of revolution time more complicated. (Unfortunately the callback isn't passed the
    # value that triggered it, so we can't just skip edges we don't want.)
    # Bug report: https://sourceforge.net/p/raspberry-gpio-python/tickets/79/
    GPIO.add_event_detect(C.WIND_INPUT_PIN(), GPIO.BOTH,
                          callback=callback, bouncetime=C.WIND_DEBOUNCE_MILLIS())

  def get_values(self):
    return K.WIND_KEY, {K.WIND_STARTUP_TIME_KEY: self._startup_time,
                        K.WIND_UP_TO_TIME_KEY: time.time(),
                        K.WIND_REVOLUTIONS_KEY: self._revolutions.get_and_reset()}

  def terminate(self):
    """Unregister the callback and, if MODE_CALIBRATE, terminate the logger."""
    GPIO.cleanup()
    if self._mode == Wind.MODE_CALIBRATE:
      self._calibration_logger.terminate()
    self._log.info('terminated')
