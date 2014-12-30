# TODO: Module should be named ipa.logging.

import logging.handlers
import os

import C


_LOG_DIR = '../logs/'
_LOG_EXT = '.log'
_LOG_FORMATS = {'ipa': '%(asctime)s - %(name)-10s - %(levelname)-8s - %(message)s',
                'wind_calibrate': '%(message)s'}
# The logger hierarchy is built lazily. Only toplevel loggers have handlers. Lower-level loggers
# propagate to toplevel loggers. We remember toplevel loggers to not add handlers twice.
_TOPLEVEL_LOGGERS = set()


def get_logger(name):
  """Return the logger with the specified name, creating it if required."""
  i = name.find('.')
  if i < 0:
    return _get_toplevel_logger(name)
  _get_toplevel_logger(name[:i])  # make sure it exists
  return logging.getLogger(name)


def _get_toplevel_logger(name):
  """Return a toplevel logger (e.g. 'ipa'), which propagates its configuration to lower-level
  loggers (e.g. 'ipa.wind') by means of the logging framework. Create and configure the logger if it
  does not exist yet."""
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
  #   case this doesn't hold for typical rotation intervals.
  # - It doesn't limit file size.
  file_handler = logging.handlers.RotatingFileHandler(
      _LOG_DIR + name + _LOG_EXT,
      maxBytes = C.LOGGING_MAX_BYTES(),
      backupCount = C.LOGGING_BACKUP_COUNT())
  log_format = _LOG_FORMATS[name]  # format must be defined
  file_handler.setFormatter(logging.Formatter(log_format))
  logger.addHandler(file_handler)
  console_handler = logging.StreamHandler()
  console_handler.setFormatter(logging.Formatter(log_format))
  logger.addHandler(console_handler)
  _TOPLEVEL_LOGGERS.add(name)
  return logger
