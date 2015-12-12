import RPi.GPIO as GPIO  #@UnresolvedImport
import threading

import common
from config import C
import log


class Door:
  """Monitors the shed door via GPIO."""

  _PIN = C.DOOR_INPUT_PIN()
  _OPEN_STATE = C.DOOR_OPEN_STATE()

  # TODO: Make these configurable? They might depend on cable layout etc.
  _STABLE_READ_INTERVAL_MILLIS = 25
  _STABLE_READ_COUNT = 5  # 20 was OK, but I believe sometimes too slow.

  def __init__(self):
    self._log = log.get_logger('ipa.door')
    self._lock = threading.Lock()
    self._events = []
    self._previous_door_open = None
    GPIO.setup(Door._PIN, GPIO.IN, pull_up_down=C.DOOR_PUD())
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
      self._consider_door_open_locked(door_open)
    return door_open

  def get_sample(self):
    # We don't want to rely on the callback; if we miss one edge (e.g. because the switch is
    # bouncing) we'd be stuck with an incorrect state until the next callback happens. So we do a
    # simple read here, accepting that we may be unlucky and hit noise on the cable, but that seems
    # much less likely that the first case. It would also very likely be corrected in the next call.
    door_open = 1 if GPIO.input(Door._PIN) == Door._OPEN_STATE else 0
    self._log.debug('door_open in get_sample: %d' % door_open)
    with self._lock:
      self._consider_door_open_locked(door_open)
      events = self._events
      self._events = []
    return 'door', events

  def _consider_door_open_locked(self, door_open):
    """Append new door_open state if it differs from the previous state. Must hold lock."""
    if door_open != self._previous_door_open:
      self._events.append((common.timestamp(), door_open))
      self._previous_door_open = door_open
