import sys

import common
import K
import raspisys


class Metadata:
  """Provides metadata such as the current time and stratum."""

  def __init__(self):
    self._md5 = sys.argv[1] if len(sys.argv) > 1 else 'n/a'

  def get_sample(self):
    return K.META_KEY, {# TODO: Avoid subprocess?
                        K.STRATUM_KEY: raspisys.get_stratum(),
                        K.CLIENT_MD5: self._md5,
                        K.CLIENT_TIMESTAMP_KEY: common.timestamp()}
