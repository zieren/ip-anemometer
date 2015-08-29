import common
import raspisys


class Temperature:
  """Provides CPU temperature."""

  def get_sample(self):
    return 'temp', (common.timestamp(), raspisys.get_temperature())
