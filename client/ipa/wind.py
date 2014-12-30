import RPi.GPIO as GPIO  #@UnresolvedImport

import C
import calibration_logger
import common
import K
import log
import wind_sensor
import wind_stats


class Wind:
  """Provides wind speed. Measurement will start immediately on creation."""

  def __init__(self, calibration_mode=False):
    self._log = log.get_logger('ipa.wind')
    self._revolutions = wind_sensor.Revolutions()
    self._startup_time = common.timestamp()
    # TODO: Consider removing start timestamp and only use sample start/end timestamps.
    self._calibration_mode = calibration_mode
    if calibration_mode:
      self._calibration_logger = calibration_logger.CalibrationLogger()
      self._revolutions.calibration_init(self._calibration_logger)
      self._register_callback(self._revolutions.calibration_add_edge_and_log)
    else:
      self._register_callback(self._revolutions.add_edge)
      self._calc = wind_stats.WindStatsCalculator(self._startup_time)
    self._log.info('initialized - CALIBRATION MODE' if calibration_mode else 'initialized')

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
    if self._calibration_mode:
      raise RuntimeError('get_sample() is not supported in calibration mode')
    up_to_time = common.timestamp()
    revs = self._revolutions.get_and_reset()
    if revs:
      # In case there was another edge after we read the time, update up_to_time. It would be odd
      # for window N to contain a timestamp in window N+1. (Technically up_to_time should be
      # exclusive, so setting it to the time of the last revolution is incorrect. But we can ignore
      # this since if the same timestamp were to occur again (revolution of zero duration...) it
      # would be ignored anyway, and any larger timestamp would correctly fall into the next
      # sample.)
      up_to_time = max(up_to_time, revs[-1])
    for ts in revs:
      self._calc.next_timestamp(ts)
    stats = self._calc.get_stats_and_reset(up_to_time)
    if not stats:  # no (new) stats available yet
      return K.WIND_KEY, None
    sample = {K.WIND_STARTUP_TIME_KEY: self._startup_time,
              K.WIND_UP_TO_TIME_KEY: up_to_time,
              K.WIND_AGGREGATE_STATS_KEY: stats}
    return K.WIND_KEY, sample

  def terminate(self):
    """Unregister the callback and, if self._calibration_mode, terminate the logger."""
    GPIO.cleanup()
    if self._calibration_mode:
      self._calibration_logger.terminate()
    self._log.info('terminated')
