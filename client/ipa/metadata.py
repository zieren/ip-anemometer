import common
import raspisys


class Metadata:
  """Provides metadata such as the current time and stratum."""

  def __init__(self):
    self._md5 = common.client_md5()

  def get_sample(self):
    return 'meta', {'stratum': raspisys.get_stratum(),  # TODO: Avoid subprocess?
                    'md5': self._md5,
                    'cts': common.timestamp()}
