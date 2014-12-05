import common
import K
import raspisys


class Temperature:
  """Provides onboard temperature."""

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    return K.TEMP_KEY, (common.timestamp(), raspisys.get_temperature())
