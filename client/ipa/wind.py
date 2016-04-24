import RPi.GPIO as GPIO  #@UnresolvedImport

import calibration_logger
import common
from config import C
from logger import LOGGER_FACTORY
import wind_revolutions
import wind_stats


class Wind:
  """Provides wind speed. Measurement will start immediately on creation."""

  def __init__(self, calibration_mode=False):
    self._log = LOGGER_FACTORY.get_logger('ipa.wind')
    self._revolutions = wind_revolutions.Revolutions(C.WIND_EDGES_PER_REV())
    self._startup_time = common.timestamp()
    # TODO: Consider removing start timestamp and only use sample start/end timestamps.
    self._calibration_mode = calibration_mode
    if calibration_mode:
      self._calibration_logger = calibration_logger.CalibrationLogger()
      self._revolutions.calibration_init(self._calibration_logger)
      self._register_callback(self._revolutions.calibration_add_edge_and_log)
    else:
      self._register_callback(self._revolutions.add_edge)
      self._stats = wind_stats.WindStats(C.WIND_HSF(), C.WIND_LSF(), C.WIND_MAX_ROTATION(),
                                         self._startup_time)
    self._log.info('initialized - CALIBRATION MODE' if calibration_mode else 'initialized')
    self._log.info('pin=%d edges=%d debounce=%dms LSF=%g HSF=%g max=%dms' % (
        C.WIND_INPUT_PIN(), C.WIND_EDGES_PER_REV(), C.WIND_DEBOUNCE_MILLIS(), C.WIND_LSF(),
        C.WIND_HSF(), C.WIND_MAX_ROTATION()))

  def _register_callback(self, callback):
    GPIO.setup(C.WIND_INPUT_PIN(), GPIO.IN, pull_up_down=C.WIND_PUD())
    # Up to (at least) GPIO 0.5.9 edge detection is partly broken. RISING always behaves like BOTH,
    # FALLING sometimes behaves like BOTH. Since BOTH is the only mode that works as expected, we
    # use BOTH even though that makes computation of revolution time more complicated.
    # (Unfortunately the callback isn't passed the value that triggered it, so we can't just skip
    # edges we don't want.)
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
      self._stats.next_timestamp(ts)
    return 'wind', self._stats.get_stats_and_reset(up_to_time)

  def terminate_calibration(self):
    self._calibration_logger.terminate()
