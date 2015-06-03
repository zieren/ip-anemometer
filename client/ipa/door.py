import RPi.GPIO as GPIO  #@UnresolvedImport
import threading

import common
from config import C
import log


class Door:
  """Monitors the shed door via GPIO."""

  _PIN = C.DOOR_INPUT_PIN()
  _OPEN_STATE = C.DOOR_OPEN_STATE()

  # TODO: Make these configurable?
  _STABLE_READ_INTERVAL_MILLIS = 25
  _STABLE_READ_COUNT = 20

  def __init__(self):
    self._log = log.get_logger('ipa.door')
    self._lock = threading.Lock()
    self._events = []
    GPIO.setup(Door._PIN, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
    GPIO.add_event_detect(Door._PIN, GPIO.BOTH, callback=self._read_door_callback,
                          bouncetime=C.DOOR_DEBOUNCE_MILLIS())

    door_open = self._read_door_callback(0)
    self._log.info('initialized (pin=%d open=%d debounce=%d)'
                   % (Door._PIN, door_open, C.DOOR_DEBOUNCE_MILLIS()))

  def _read_door_callback(self, pin_ignored):
    read_stable = common.read_stable(Door._PIN, Door._STABLE_READ_COUNT,
                                     Door._STABLE_READ_INTERVAL_MILLIS, self._log)
    door_open = 1 if read_stable == Door._OPEN_STATE else 0
    self._log.debug('door_open in callback: %d' % door_open)
    with self._lock:
      self._events.append((common.timestamp(), door_open))
    return door_open

  def get_sample(self):
    with self._lock:
      events = self._events
      self._events = []
    # TODO: Remove this redundant read if the stable read is really stable :-)
    door_open = 1 if GPIO.input(Door._PIN) == Door._OPEN_STATE else 0
    events.append((common.timestamp(), door_open))
    self._log.debug('door_open in get_sample: %d' % door_open)
    # TODO: Enable this when it's otherwise working reliably.
#     # Remove duplicates.
#     events_compressed = []
#     for e in events:
#       if not events_compressed or events_compressed[-1][1] != e[1]:  # compare state (index 1)
#         events_compressed.append[e]
    return 'door', events
