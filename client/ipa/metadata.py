from args import ARGS
import common
import raspisys


class Metadata:
  """Provides metadata such as the current time and stratum."""

  def get_sample(self):
    return 'meta', {'stratum': raspisys.get_stratum(),  # TODO: Avoid subprocess?
                    'md5': ARGS.archive_md5(),
                    'cts': common.timestamp()}
