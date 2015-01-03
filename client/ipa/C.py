import ConfigParser
import threading

import K


_CFG = ConfigParser.SafeConfigParser()
_CFG.read(K.CONFIG_FILENAME)
_LOCK = threading.Lock()
# SafeConfigParser doesn't say it's thread-safe, so we're paranoid and synchronize all access.
# When these values are eventually overridable by the server this will be needed anyway.
# TODO: Support server-side overriding of values.


_SECTION_UPLOAD = 'Upload'
def UPLOAD_URL():
  """Ensures a trailing '/'."""
  with _LOCK:
    url = _CFG.get(_SECTION_UPLOAD, 'url')
    if url[-1] != '/':
      url += '/'
    return url
def UPLOAD_USERNAME():
  with _LOCK: return _CFG.get(_SECTION_UPLOAD, 'username')
def UPLOAD_PASSWORD():
  with _LOCK: return _CFG.get(_SECTION_UPLOAD, 'password')
def UPLOAD_INTERVAL_SECONDS():
  with _LOCK: return _CFG.getfloat(_SECTION_UPLOAD, 'interval_seconds')
def UPLOAD_MAX_SIZE_KB():
  with _LOCK: return _CFG.getint(_SECTION_UPLOAD, 'max_size_kb')

_SECTION_TIMEOUTS = 'Timeouts'
def TIMEOUT_SHUTDOWN_SECONDS():
  with _LOCK: return _CFG.getfloat(_SECTION_TIMEOUTS, 'shutdown_seconds')
def TIMEOUT_HTTP_REQUEST_SECONDS():
  with _LOCK: return _CFG.getfloat(_SECTION_TIMEOUTS, 'http_request_seconds')

_SECTION_WIND = 'Wind'
def WIND_DEBOUNCE_MILLIS():
  with _LOCK: return _CFG.getint(_SECTION_WIND, 'debounce_millis')
def WIND_INPUT_PIN():
  with _LOCK: return _CFG.getint(_SECTION_WIND, 'input_pin')
def WIND_EDGES_PER_REV():
  with _LOCK: return _CFG.getint(_SECTION_WIND, 'edges_per_revolution')
def WIND_HSF():
  with _LOCK: return _CFG.getfloat(_SECTION_WIND, 'high_speed_factor')
def WIND_LSF():
  with _LOCK: return _CFG.getfloat(_SECTION_WIND, 'low_speed_factor')
def WIND_MAX_ROTATION():
  with _LOCK: return long(_CFG.getfloat(_SECTION_WIND, 'max_rotation_seconds') * 1000)

_SECTION_LOGGING = 'Logging'
def LOGGING_LEVEL():
  with _LOCK: return _CFG.get(_SECTION_LOGGING, 'level')
def LOGGING_MAX_FILE_SIZE_KB():
  with _LOCK: return _CFG.getint(_SECTION_LOGGING, 'max_file_size_kb')
def LOGGING_BACKUP_COUNT():
  with _LOCK: return _CFG.getint(_SECTION_LOGGING, 'backup_count')
