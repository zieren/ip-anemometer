import RPi.GPIO as GPIO  #@UnresolvedImport
import os
import threading
import time

import K
import common
from config import C
import log
import raspisys


_STATE_FILE = K.STATE_DIR + 'pilot_count.txt'
_STATE_LIFETIME_MINUTES = 12 * 60  # ignore persisted state when older than 12h


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

  _LED_ON_MILLIS = 200
  _LED_OFF_MILLIS = 300
  _LED_ON_SHORT_MILLIS = 20
  _LED_OFF_SHORT_MILLIS = 40

  def __init__(self):
    self._log = log.get_logger('ipa.count')

    # Only one blinking thread at a time.
    self._blink_semaphore = threading.Semaphore()

    # Synchronize access in _read_callback() and get_sample().
    self._lock = threading.Lock()
    self._count = 0
    self._pilots = []
    self._last_reset_yday = time.localtime(time.time()).tm_yday

    self._log.info(('initialized (plus: pin=%d trigger=%d debounce=%d; '
                    + 'minus: pin=%d trigger=%d debounce=%d)')
                   % (PilotCount._PLUS_PIN, PilotCount._PLUS_TRIGGER_STATE,
                      C.PILOTS_PLUS_DEBOUNCE_MILLIS(), PilotCount._MINUS_PIN,
                      PilotCount._MINUS_TRIGGER_STATE, C.PILOTS_MINUS_DEBOUNCE_MILLIS()))

    # Read previous count if it's fresh.
    try:
      mtime = os.stat(_STATE_FILE).st_mtime
      if (time.time() - mtime) / 60.0 < _STATE_LIFETIME_MINUTES:
        with open(_STATE_FILE) as f:
          self._count = int(f.read())
        with self._lock:  # not really necessary at this point
          self._append_count_locked()
        self._log.info('read pilot count from file: %d @ %s'
                       % (self._count, time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(mtime))))
    except Exception:
      self._log.warning('could not read state from file %s' % _STATE_FILE)

    GPIO.setup(PilotCount._PLUS_PIN, GPIO.IN,
               pull_up_down=GPIO.PUD_DOWN if PilotCount._PLUS_TRIGGER_STATE else GPIO.PUD_UP)
    GPIO.setup(PilotCount._MINUS_PIN, GPIO.IN,
               pull_up_down=GPIO.PUD_DOWN if PilotCount._MINUS_TRIGGER_STATE else GPIO.PUD_UP)
    GPIO.setup(PilotCount._LED_PIN, GPIO.OUT)
    GPIO.add_event_detect(PilotCount._PLUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.PILOTS_PLUS_DEBOUNCE_MILLIS())
    GPIO.add_event_detect(PilotCount._MINUS_PIN, GPIO.BOTH, callback=self._read_callback,
                          bouncetime=C.PILOTS_MINUS_DEBOUNCE_MILLIS())

  def _read_callback(self, pin):
    read_stable = raspisys.read_stable(pin, PilotCount._STABLE_READ_COUNT,
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
      if self._blink_semaphore.acquire(False):  # If we're currently blinking, ignore this event.
        self._reset_at_night_locked()
        self._count = max(0, self._count + delta)
        self._log.debug('count += %d -> %d' % (delta, self._count))
        self._update_pilots_locked()
        BlinkThread(self._count, self._blink_semaphore).start()

  def _reset_at_night_locked(self):
    t = time.localtime(time.time())
    if t.tm_hour >= PilotCount._RESET_HOUR and t.tm_yday != self._last_reset_yday:
      if self._count:
        self._log.debug('resetting counter to 0 (was: %d)' % self._count)
        self._count = 0
      self._update_pilots_locked()  # Avoid long blanks in the DB.
      self._last_reset_yday = t.tm_yday

  def _update_pilots_locked(self):
    self._append_count_locked()
    with open(_STATE_FILE, 'w') as f:
      f.write(str(self._count))

  def _append_count_locked(self):
    self._pilots.append((common.timestamp(), self._count))

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
    if self._count:
      self._flash_led(self._count, PilotCount._LED_ON_MILLIS, PilotCount._LED_OFF_MILLIS)
    else:
      self._flash_led(4, PilotCount._LED_ON_SHORT_MILLIS, PilotCount._LED_OFF_SHORT_MILLIS)
    self._blink_semaphore.release()

  def _flash_led(self, times, on_millis, off_millis):
    for i in range(times):
      if i:
        time.sleep(off_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 1)
      time.sleep(on_millis / 1000.0)
      GPIO.output(PilotCount._LED_PIN, 0)
