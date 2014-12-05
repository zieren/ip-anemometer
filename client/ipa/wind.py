import RPi.GPIO as GPIO  #@UnresolvedImport

import C
import calibration_logger
import common
import K
import wind_sensor
import wind_stats

class Wind:
  """Provides wind speed. Measurement will start immediately on creation."""

  # TODO: Remove MODE_BOTH_DEBUG
  MODE_PRECISION, MODE_AGGREGATE, MODE_CALIBRATE, MODE_BOTH_DEBUG = range(4)
  _mode_to_string = {
     MODE_PRECISION: 'precision', MODE_AGGREGATE: 'aggregate', MODE_CALIBRATE: 'calibrate',
     MODE_BOTH_DEBUG: 'precision+aggregate (DEBUG)'
  }

  def __init__(self, mode):
    self._log = K.get_logger(K.LOG_NAME_WIND)
    self._revolutions = wind_sensor.Revolutions()
    self._mode = mode
    self._startup_time = common.timestamp()
    if mode == Wind.MODE_PRECISION:
      self._register_callback(self._revolutions.add_edge)
    elif mode == Wind.MODE_AGGREGATE:
      self._register_callback(self._revolutions.add_edge)
      self._calc = wind_stats.WindStatsCalculator(self._startup_time)
    elif mode == Wind.MODE_BOTH_DEBUG:
      self._register_callback(self._revolutions.add_edge)
      self._calc = wind_stats.WindStatsCalculator(self._startup_time)
    elif mode == Wind.MODE_CALIBRATE:
      self._calibration_logger = calibration_logger.CalibrationLogger()
      self._revolutions.calibration_init(self._calibration_logger)
      self._register_callback(self._revolutions.calibration_add_edge_and_log)
    else:
      raise RuntimeError('invalid mode: %d' % mode)
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

  def get_sample(self):
    if self._mode == Wind.MODE_CALIBRATE:
      raise RuntimeError('get_sample() is not supported in calibration mode')
    up_to_time = common.timestamp()
    revs = self._revolutions.get_and_reset()
    if revs:
      # In case there was another edge after we took the time, update up_to_time. Not really
      # relevant for precision mode, but in aggregate mode it would be odd for window N to contain
      # a timestamp in window N+1.
      up_to_time = max(up_to_time, revs[-1])
    sample = {K.WIND_STARTUP_TIME_KEY: self._startup_time,
              K.WIND_UP_TO_TIME_KEY: up_to_time}
    if self._mode == Wind.MODE_PRECISION or self._mode == Wind.MODE_BOTH_DEBUG:
      sample[K.WIND_REVOLUTIONS_KEY] = revs
    if self._mode == Wind.MODE_AGGREGATE or self._mode == Wind.MODE_BOTH_DEBUG:
      for ts in revs:
        self._calc.next_timestamp(ts)
      sample[K.WIND_AGGREGATE_STATS_KEY] = self._calc.get_stats_and_reset(up_to_time)
    return K.WIND_KEY, sample

  def terminate(self):
    """Unregister the callback and, if MODE_CALIBRATE, terminate the logger."""
    GPIO.cleanup()
    if self._mode == Wind.MODE_CALIBRATE:
      self._calibration_logger.terminate()
    self._log.info('terminated')
