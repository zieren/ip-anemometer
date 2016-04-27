import common
from config import C
import raspisys


class Metadata:
  """Provides metadata such as the current time and stratum."""

  def get_sample(self):
    return 'meta', {'stratum': raspisys.get_stratum(),  # TODO: Avoid subprocess?
                    'client_version': C.CLIENT_VERSION(),
                    'cts': common.timestamp()}
