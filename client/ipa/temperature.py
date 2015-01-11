import common
import raspisys


class Temperature:
  """Provides CPU temperature."""

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    return 'temp', (common.timestamp(), raspisys.get_temperature())
