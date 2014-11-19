# -*- coding: utf-8 -*-

import logging.handlers
import os

import C


# ----- Client/server interaction. NOTE: These must match common.php -----

TIMESTAMP_KEY = 'ts'
VALUE_KEY = 'v'
META_KEY = 'meta'
TEMP_KEY = 'temp'
WIND_KEY = 'wind'
WIND_STARTUP_TIME_KEY = 'wind_startup'
WIND_UP_TO_TIME_KEY = 'wind_up_to'
WIND_REVOLUTIONS_KEY = 'wind_revs'
WIND_AGGREGATE_STATS_KEY = 'wind_stats'
UPLOAD_KEY = 'upload'
CLIENT_MD5_KEY = 'md5'
STRATUM_KEY = 'stratum'
STARTUP_TIME_KEY = 'startup'
CLIENT_TIMESTAMP_KEY = 'cts'
FAILED_UPLOADS_KEY = 'ulfs'
UPLOAD_POST_KEY = 'bz2'
COMMAND_RESTART = 'restart'
COMMAND_SHUTDOWN = 'shutdown'
COMMAND_REBOOT = 'reboot'
RESPONSE_STATUS = 'status'
RESPONSE_STATUS_OK = 'ok'


# ----- Client constants -----

CONFIG_FILENAME = 'ipa.cfg'
RESPONSE_STATUS_UNKNOWN = 'N/A'
CLIENT_GREETING = 'IP anemometer client 0.0.3 - (c) Jörg Zieren - http://zieren.de - GNU GPL v3'
RETURN_VALUE_SHUTDOWN = 100
RETURN_VALUE_REBOOT = 101


# ----- Logging -----

# LOG_NAME_* should be <= 10 characters for proper alignment.

LOG_NAME_MAIN = 'ipa'
LOG_NAME_TEMP = 'ipa.temp'
LOG_NAME_WIND = 'ipa.wind'
LOG_NAME_METADATA = 'ipa.meta'
LOG_NAME_UPLOADER = 'ipa.upload'

LOG_NAME_NTPDSYNC = 'ntpdsync'


_LOG_DIR = './logs/'
_LOG_EXT = '.log'
_LOG_FORMAT = '%(asctime)s - %(name)-10s - %(levelname)-8s - %(message)s'

# NOTE: The logger hierarchy is built lazily. Only toplevel loggers have handlers. Lower-level
# loggers propagate to toplevel loggers. We remember toplevel loggers to not add handlers twice.
_TOPLEVEL_LOGGERS = set()

def _get_toplevel_logger(name, log_format):
  '''
  Returns a toplevel logger (e.g. 'ipa'), which propagates its configuration to lower-level loggers
  (e.g. 'ipa.wind') by means of the logging framework. Creates and configures the logger if it does
  not exist yet.
  '''
  logger = logging.getLogger(name);
  if name in _TOPLEVEL_LOGGERS:
    return logger
  if not os.path.exists(_LOG_DIR):
    os.mkdir(_LOG_DIR)
  logger.setLevel(C.LOGGING_LEVEL())
  # We use RotatingFileHandler because TimedRotatingFileHandler has two drawbacks:
  # - It doesn't rotate on startup, i.e. the process needs to be running at the rotation time (and
  #   then emit after the rotation time). This works well when the rotation interval is small
  #   compared to the process runtime. But we might be running on a timer/daylight switch, in which
  #   case this doesn't hold for reasonable rotation intervals.
  # - It doesn't limit file size.
  file_handler = logging.handlers.RotatingFileHandler(
      _LOG_DIR + name + _LOG_EXT,
      maxBytes = C.LOGGING_MAX_BYTES(),
      backupCount = C.LOGGING_BACKUP_COUNT())
  file_handler.setFormatter(logging.Formatter(log_format))
  logger.addHandler(file_handler)
  console_handler = logging.StreamHandler()
  console_handler.setFormatter(logging.Formatter(log_format))
  logger.addHandler(console_handler)
  _TOPLEVEL_LOGGERS.add(name)
  return logger


def get_logger(name, log_format = _LOG_FORMAT):
  i = name.find('.')
  if i < 0:
    return _get_toplevel_logger(name, log_format)
  _get_toplevel_logger(name[:i], log_format)  # make sure it exists
  return logging.getLogger(name)


# ----- Calibration -----

LOG_NAME_CALIBRATION = 'wind_calibrate'
LOG_FORMAT_CALIBRATION = '%(message)s'
