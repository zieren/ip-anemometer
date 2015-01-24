import RPi.GPIO as GPIO  #@UnresolvedImport
import threading

import common
from config import C
import log


class Door:
  """Monitors the shed door via GPIO."""

  _PIN = C.DOOR_INPUT_PIN()
  _OPEN_STATE = C.DOOR_OPEN_STATE()

  def __init__(self):
    self._log = log.get_logger('ipa.door')
    self._lock = threading.Lock()
    self._events = []
    GPIO.setup(Door._PIN, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    GPIO.add_event_detect(Door._PIN, GPIO.BOTH, callback=self._set_door,
                          bouncetime=C.DOOR_DEBOUNCE_MILLIS())

    self._set_door(Door._PIN)
    self._log.info('initialized (pin=%d)' % Door._PIN)

  def _set_door(self, pin):
    with self._lock:
      self._events.append((common.timestamp(),
                           1 if GPIO.input(Door._PIN) == Door._OPEN_STATE else 0))

  def get_sample(self):
    with self._lock:
      events = self._events
      self._events = []
    return 'door', events
