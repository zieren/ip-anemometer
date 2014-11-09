import re
import subprocess


_STRATUM_PATTERN = re.compile(r'stratum=(\d+)$')

def get_stratum():
  """Returns the stratum."""
  m = _STRATUM_PATTERN.match(subprocess.check_output(['ntpq', '-c', 'rv 0 stratum']))
  return int(m.group(1)) if m else 16  # 16 = "unsynchronized"

_TEMPERATURE_CMD = ['/opt/vc/bin/vcgencmd', 'measure_temp']
_TEMPERATURE_PATTERN = re.compile(r"temp=([0-9\.]+)'C$")

def get_temperature():
  """Returns the onboard temperature."""
  output = subprocess.check_output(_TEMPERATURE_CMD)
  m = _TEMPERATURE_PATTERN.match(output)
  return m.group(1) if m else '666.0'  # should never happen
