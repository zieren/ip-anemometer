import common
from demo_util import random_value


class Door:

  def __init__(self):
    self._door_open = int(random_value(0, 2))
    self._events = [(common.timestamp(), self._door_open)]

  def get_sample(self):
    if random_value(0, 10) < 1:
      self._door_open = 1 - self._door_open
      self._events.append((common.timestamp(), self._door_open))
    events = self._events
    self._events = []
    return 'door', events
