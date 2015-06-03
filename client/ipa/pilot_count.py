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

  def __init__(self):
    self._log = log.get_logger('ipa.count')
    self._lock = threading.Lock()
    self._events = []  # Button events sent to server. Protected by self._lock.
    self._count = 0  # Current count. Protected by self._lock.
    # Whether we accept input (normal) or not (LED output after input). Protected by self._lock.
    self._accept_input = True

    # XXX locked?
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
    with self._lock:
      if not self._accept_input:
        return
      self._accept_input = False  # so further event threads return immediately
    # XXX CHANGE THE LOCKING: Should do self._lock.acquire(False) (because of
    # get_sample()).
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
      self._set_accept_input()
      return

    self._reset_at_night()

    self._count = max(0, self._count + delta)
    self._log.debug('count += %d -> %d' % (delta, self._count))
    self._events.append((common.timestamp(), delta))
    self._output_to_led()
    self._set_accept_input()

  def _set_accept_input(self):
    # In theory we could starve here when events never stop. In practice we ignore this :-)
    with self._lock:
      self._accept_input = True

  def _reset_at_night(self):
    t = time.localtime(time.time())
    if t.tm_hour >= PilotCount._RESET_HOUR and t.tm_yday != self._last_reset_yday:
      self._log.debug('resetting counter to 0 (was: %d)' % self._count)
      self._count = 0
      self._last_reset_yday = t.tm_yday

  def _output_to_led(self):
    if self._count:
      self._flash_led(self._count, 200, 300)
    else:
      self._flash_led(4, 20, 40)

  def _flash_led(self, times, on_millis, off_millis):
    for i in range(times):
      if i != 0:
        time.sleep(off_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 1)
      time.sleep(on_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 0)

  def get_sample(self):
    with self._lock:
      self._reset_at_night()
      events = self._events
      self._events = []
    return 'count', events
