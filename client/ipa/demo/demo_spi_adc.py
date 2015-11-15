import common
from demo_util import random_value


class SpiAdc:

  def __init__(self):
    self._channels = [7]

  def get_sample(self):
    sample = {}
    for c in self._channels:
      sample[c] = (common.timestamp(), random_value(10, 15))
    return 'adc', sample
