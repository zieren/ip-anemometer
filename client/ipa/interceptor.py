import common
from config import C
import log

MAX_TEMP = C.TEMPERATURE_SHUTDOWN_AT()
LOG = log.get_logger('ipa.intcpt')

def process(queue):
  """Process the queue, possibly modifying it. May return a command."""
  command = None
  last_temp = _get_last_sample(queue, 'temp')
  if last_temp and last_temp[1] >= MAX_TEMP:
    LOG.info('shutting down since temperature of %s exceeds maximum of %s' %
             (last_temp[1], MAX_TEMP))
    queue['status'] = (common.timestamp(),
                       'shutdown: CPU temp = %s (max: %s)' % (last_temp[1], MAX_TEMP))
    command = ('exit', 100)  # 100 = shutdown
  else:
    queue['status'] = (common.timestamp(), 'ok')

  return command

def _get_last_sample(queue, type_key):
  samples = queue.get(type_key, [])
  return samples[-1] if samples else None
