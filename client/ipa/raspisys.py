import operator
import RPi.GPIO as GPIO  #@UnresolvedImport
import re
import subprocess
import time


_STRATUM_PATTERN = re.compile(r'stratum=(\d+)$')
_TEMPERATURE_CMD = ['/opt/vc/bin/vcgencmd', 'measure_temp']
_TEMPERATURE_PATTERN = re.compile(r"temp=([0-9\.]+)'C$")


def get_stratum():
  """Return the stratum."""

  m = _STRATUM_PATTERN.match(subprocess.check_output(['ntpq', '-c', 'rv 0 stratum']))
  return int(m.group(1)) if m else 16  # 16 = "unsynchronized"


def get_temperature():
  """Return CPU temperature."""

  output = subprocess.check_output(_TEMPERATURE_CMD)
  m = _TEMPERATURE_PATTERN.match(output)
  return float(m.group(1)) if m else 666.0  # should never happen


def read_stable(pin, num, interval_millis, log = None):
  """Repeatedly read the specified GPIO pin (to filter cable noise)."""
  reads = {}
  for _ in range(num):
    if reads:
      time.sleep(interval_millis / 1000.0)
    r = GPIO.input(pin)
    reads[r] = reads.get(r, 0) + 1
  result = max(reads.iteritems(), key=operator.itemgetter(1))[0]
  if log:
    log.debug('read_stable: %s -> %d' % (reads, result))
  return result
