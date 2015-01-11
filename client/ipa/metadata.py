import common
import K
import raspisys


class Metadata:
  """Provides metadata such as the current time and stratum."""

  def __init__(self):
    self._md5 = common.client_md5()

  def get_sample(self):
    return 'meta', {K.STRATUM_KEY: raspisys.get_stratum(),  # TODO: Avoid subprocess?
                    K.CLIENT_MD5: self._md5,
                    'cts': common.timestamp()}
