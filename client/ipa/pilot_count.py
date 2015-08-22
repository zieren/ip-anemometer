import RPi.GPIO as GPIO  #@UnresolvedImport
import threading
import time

import common
from config import C
import log


class PilotCount:
  """Counts pilots by manually pressing a button connected to GPIO."""

  _PLUS_PIN = C.PILOTS_PLUS_INPUT_PIN()
  _MINUS_PIN = C.PILOTS_MINUS_INPUT_PIN()
  _PLUS_TRIGGER_STATE = C.PILOTS_PLUS_TRIGGER_STATE()
  _MINUS_TRIGGER_STATE = C.PILOTS_MINUS_TRIGGER_STATE()
  _LED_PIN = C.PILOTS_LED_OUTPUT_PIN()
  _RESET_HOUR = C.PILOTS_RESET_HOUR()

  # TODO: Make these configurable? They might depend on cable layout etc.
  _STABLE_READ_INTERVAL_MILLIS = 20
  _STABLE_READ_COUNT = 5

  # TODO: Also configurable?
  _LED_ON_MILLIS = 200
  _LED_OFF_MILLIS = 300
  _LED_ON_SHORT_MILLIS = 20
  _LED_OFF_SHORT_MILLIS = 40
  _TRAILING_PAUSE_MILLIS = 1000

  def __init__(self):
    self._log = log.get_logger('ipa.count')

    # Only one blinking thread at a time.
    self._blink_semaphore = threading.Semaphore()

    # Synchronize access in _read_callback() and get_sample().
    self._lock = threading.Lock()
    self._count = 0
    self._pilots = []
    self._last_reset_yday = time.localtime(time.time()).tm_yday

    GPIO.setup(PilotCount._PLUS_PIN, GPIO.IN,
               pull_up_down=GPIO.PUD_DOWN if PilotCount._PLUS_TRIGGER_STATE else GPIO.PUD_UP)
    GPIO.setup(PilotCount._MINUS_PIN, GPIO.IN,
               pull_up_down=GPIO.PUD_DOWN if PilotCount._MINUS_TRIGGER_STATE else GPIO.PUD_UP)
    GPIO.setup(PilotCount._LED_PIN, GPIO.OUT)
    GPIO.add_event_detect(PilotCount._PLUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.PILOTS_PLUS_DEBOUNCE_MILLIS())
    GPIO.add_event_detect(PilotCount._MINUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.PILOTS_MINUS_DEBOUNCE_MILLIS())
    self._log.info(('initialized (plus: pin=%d trigger=%d debounce=%d; '
                    + 'minus: pin=%d trigger=%d debounce=%d)')
                   % (PilotCount._PLUS_PIN, PilotCount._PLUS_TRIGGER_STATE,
                      C.PILOTS_PLUS_DEBOUNCE_MILLIS(), PilotCount._MINUS_PIN,
                      PilotCount._MINUS_TRIGGER_STATE, C.PILOTS_MINUS_DEBOUNCE_MILLIS()))

  def _read_callback(self, pin):
    read_stable = common.read_stable(pin, PilotCount._STABLE_READ_COUNT,
                                     PilotCount._STABLE_READ_INTERVAL_MILLIS, self._log)
    delta = 0
    if pin == PilotCount._PLUS_PIN:
      if read_stable == PilotCount._PLUS_TRIGGER_STATE:
        delta = 1
    elif pin == PilotCount._MINUS_PIN:
      if read_stable == PilotCount._MINUS_TRIGGER_STATE:
        delta = -1
    if not delta:
      return

    with self._lock:
      self._reset_at_night_locked()
      self._count = max(0, self._count + delta)
      self._log.debug('count += %d -> %d' % (delta, self._count))
      self._pilots.append((common.timestamp(), self._count))
      # In theory we'd have to wait here until the semaphore is acquired to avoid a race against the
      # next event. But we rely on Python starting threads fast enough :-)
      BlinkThread(self._count, self._blink_semaphore).start()

  def _reset_at_night_locked(self):
    """Must hold lock."""
    if not self._count:
      return
    t = time.localtime(time.time())
    if t.tm_hour >= PilotCount._RESET_HOUR and t.tm_yday != self._last_reset_yday:
      self._log.debug('resetting counter to 0 (was: %d)' % self._count)
      self._count = 0
      self._pilots.append((common.timestamp(), self._count))
      self._last_reset_yday = t.tm_yday

  def get_sample(self):
    with self._lock:
      self._reset_at_night_locked()
      pilots = self._pilots
      self._pilots = []
    return 'pilots', pilots


class BlinkThread(threading.Thread):

  def __init__(self, count, blink_semaphore):
    threading.Thread.__init__(self)
    self._count = count
    self._blink_semaphore = blink_semaphore

  def run(self):
    self._blink_semaphore.acquire()
    if self._count:
      self._flash_led(self._count, PilotCount._LED_ON_MILLIS, PilotCount._LED_OFF_MILLIS)
    else:
      self._flash_led(4, PilotCount._LED_ON_SHORT_MILLIS, PilotCount._LED_OFF_SHORT_MILLIS)
    time.sleep(PilotCount._TRAILING_PAUSE_MILLIS / 1000.0)
    self._blink_semaphore.release()

  def _flash_led(self, times, on_millis, off_millis):
    for i in range(times):
      if i:
        time.sleep(off_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 1)
      time.sleep(on_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 0)
