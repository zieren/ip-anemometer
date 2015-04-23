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
    self._lock = threading.RLock()
    self._events = []
    GPIO.setup(Door._PIN, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    GPIO.add_event_detect(Door._PIN, GPIO.BOTH, callback=self._read_door_callback,
                          bouncetime=C.DOOR_DEBOUNCE_MILLIS())

    door_open = self._read_door_internal(0)
    self._log.info('initialized (pin=%d debounce=%d)' % (Door._PIN, C.DOOR_DEBOUNCE_MILLIS()))
    self._log.debug('door_open on init: %d' % door_open)

  def _read_door_callback(self, pin_ignored):
    door_open = self._read_door_internal(pin_ignored)
    self._log.debug('door_open in callback: %d' % door_open)

  def _read_door_internal(self, pin_ignored):
    # TODO: Is it a bad idea to read here b/c of bouncing?
    door_open = 1 if GPIO.input(Door._PIN) == Door._OPEN_STATE else 0
    with self._lock:
      self._events.append((common.timestamp(), door_open))
    return door_open

  def get_sample(self):
    with self._lock:
      door_open = self._read_door_internal(0)  # TODO: Get rid of this hack.
      events = self._events
      self._events = []
    # TODO: Enable this when it's otherwise working reliably.
#     # Remove duplicates.
#     events_compressed = []
#     for e in events:
#       if not events_compressed or events_compressed[-1][1] != e[1]:  # compare state (index 1)
#         events_compressed.append[e]
    self._log.debug('door_open in get_sample(): %d' % door_open)
    return 'door', events
