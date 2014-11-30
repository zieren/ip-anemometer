import time

import K
import raspisys


class Temperature:
  """Provides onboard temperature."""

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    return K.TEMP_KEY, (int(1000 * time.time() + 0.5), raspisys.get_temperature())
