import common
from demo_util import random_value


class Dht:

  def get_sample(self):
    return 'temp_hum', (common.timestamp(), random_value(-5, 15), random_value(40, 80))
