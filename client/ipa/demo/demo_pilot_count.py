import common
from demo_util import random_value


class PilotCount:

  def __init__(self):
    self._count = 0
    self._pilots = [(common.timestamp(), 0)]

  def get_sample(self):
    if random_value(0, 10) < 1:
      self._count += 1 if random_value(0, 2) < 1 else -1
      self._count = min(self._count, 9)
      self._count = max(self._count, 0)
      self._pilots.append((common.timestamp(), self._count))
    pilots = self._pilots
    self._pilots = []
    return 'pilots', pilots
