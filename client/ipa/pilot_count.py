import RPi.GPIO as GPIO  #@UnresolvedImport
import threading
import time

import common
from config import C
import log


class PilotCount:
  """Counts pilots by manually pressing a button connected to GPIO."""

  _PLUS_PIN = C.COUNT_PLUS_INPUT_PIN()
  _MINUS_PIN = C.COUNT_MINUS_INPUT_PIN()
  _PLUS_TRIGGER_STATE = C.COUNT_PLUS_TRIGGER_STATE()
  _MINUS_TRIGGER_STATE = C.COUNT_MINUS_TRIGGER_STATE()
  _LED_PIN = C.COUNT_LED_OUTPUT_PIN()
  _RESET_HOUR = C.COUNT_RESET_HOUR()

  # TODO: Make these parameters. Plus/minus separate?
  _STABLE_READ_INTERVAL_MILLIS = 20
  _STABLE_READ_COUNT = 5

  # TODO: Also parameters?
  _LED_ON_MILLIS = 200
  _LED_OFF_MILLIS = 300
  _LED_ON_SHORT_MILLIS = 20
  _LED_OFF_SHORT_MILLIS = 40

  def __init__(self):
    self._log = log.get_logger('ipa.count')

    # Block further callbacks while processing an event.
    self._callback_lock = threading.Lock()

    # Synchronize access in _read_callback() and get_sample().
    self._lock = threading.Lock()
    self._count = 0
    self._pilots = []
    self._last_reset_yday = 0  # never; yday starts at 1

    GPIO.setup(PilotCount._PLUS_PIN, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    GPIO.setup(PilotCount._MINUS_PIN, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    GPIO.setup(PilotCount._LED_PIN, GPIO.OUT)
    GPIO.add_event_detect(PilotCount._PLUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.COUNT_PLUS_DEBOUNCE_MILLIS())
    GPIO.add_event_detect(PilotCount._MINUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.COUNT_MINUS_DEBOUNCE_MILLIS())
    self._log.info(('initialized (plus: pin=%d trigger=%d debounce=%d; '
                    + 'minus: pin=%d trigger=%d debounce=%d)')
                   % (PilotCount._PLUS_PIN, PilotCount._PLUS_TRIGGER_STATE,
                      C.COUNT_PLUS_DEBOUNCE_MILLIS(), PilotCount._MINUS_PIN,
                      PilotCount._MINUS_TRIGGER_STATE, C.COUNT_MINUS_DEBOUNCE_MILLIS()))

  def _read_callback(self, pin):
    if not self._callback_lock.acquire(False):
      return  # ignore button while previous button is still processing

    read_stable = common.read_stable(pin, PilotCount._STABLE_READ_COUNT,
                                     PilotCount._STABLE_READ_INTERVAL_MILLIS, self._log)
    delta = 0
    if pin == PilotCount._PLUS_PIN:
      if read_stable == PilotCount._PLUS_TRIGGER_STATE:
        delta = 1
    elif pin == PilotCount._MINUS_PIN:
      if read_stable == PilotCount._MINUS_TRIGGER_STATE:
        delta = -1
    else:
      self._log.critical('received callback for unrelated pin: %d' % pin)

    if not delta:
      self._callback_lock.release()
      return

    with self._lock:
      self._reset_at_night_locked()
      self._count = max(0, self._count + delta)
      self._log.debug('count += %d -> %d' % (delta, self._count))
      self._pilots.append((common.timestamp(), self._count))

    self._output_to_led()
    # We only release the lock after the LED blinking so the next event doesn't interfere with it.
    self._callback_lock.release()

  def _reset_at_night_locked(self):
    if not self._count:
      return
    t = time.localtime(time.time())
    if t.tm_hour >= PilotCount._RESET_HOUR and t.tm_yday != self._last_reset_yday:
      self._log.debug('resetting counter to 0 (was: %d)' % self._count)
      self._count = 0
      self._pilots.append((common.timestamp(), self._count))
      self._last_reset_yday = t.tm_yday

  def _output_to_led(self):
    if self._count:
      self._flash_led(self._count, PilotCount._LED_ON_MILLIS, PilotCount._LED_OFF_MILLIS)
    else:
      self._flash_led(4, PilotCount._LED_ON_SHORT_MILLIS, PilotCount._LED_OFF_SHORT_MILLIS)

  def _flash_led(self, times, on_millis, off_millis):
    for i in range(times):
      if i:
        time.sleep(off_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 1)
      time.sleep(on_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 0)

  def get_sample(self):
    with self._lock:
      self._reset_at_night_locked()
      pilots = self._pilots
      self._pilots = []
    return 'pilots', pilots
