import logging.handlers
import os


class LoggerFactory:
  """Factory for creating loggers.

  If initialize() has not been called before the first call to get_logger(), initialization values
  are retrieved from the config file. In tests initialize() should be called first.
  """

  _LOG_DIR = '../logs/'
  _LOG_EXT = '.log'
  _LOG_FORMATS = {'ipa': '%(asctime)s - %(name)-10s - %(levelname)-8s - %(message)s',
                  'wind_calibrate': '%(message)s'}
  # The logger hierarchy is built lazily. Only toplevel loggers have handlers. Lower-level loggers
  # propagate to toplevel loggers. We remember toplevel loggers to not add handlers twice.
  _TOPLEVEL_LOGGERS = set()

  def __init__(self):
    self._initialized = False

  def initialize(self, level, max_file_size_kb=0, backup_count=0):
    self._level = level
    self._max_file_size_kb = max_file_size_kb
    self._backup_count = backup_count
    self._initialized = True

  def get_logger(self, name):
    """Return the logger with the specified name, creating it if required."""

    if not self._initialized:
      from config import C
      self.initialize(C.LOGGING_LEVEL(), C.LOGGING_MAX_FILE_SIZE_KB(), C.LOGGING_BACKUP_COUNT())

    i = name.find('.')
    if i < 0:
      return self._get_toplevel_logger(name)
    self._get_toplevel_logger(name[:i])  # make sure it exists
    return logging.getLogger(name)


  def _get_toplevel_logger(self, name):
    """Return a toplevel logger (e.g. 'ipa'), which propagates its configuration to lower-level
    loggers (e.g. 'ipa.wind') by means of the logging framework. Create and configure the logger if
    it does not exist yet."""
    logger = logging.getLogger(name);
    if name in LoggerFactory._TOPLEVEL_LOGGERS:
      return logger
    logger.setLevel(self._level)
    log_format = LoggerFactory._LOG_FORMATS[name]  # format must be defined
    # We use RotatingFileHandler because TimedRotatingFileHandler has two drawbacks:
    # - It doesn't rotate on startup, i.e. the process needs to be running at the rotation time (and
    #   then emit after the rotation time). This works well when the rotation interval is small
    #   compared to the process runtime. But we might be running on a timer/daylight switch, in which
    #   case this doesn't hold for typical rotation intervals.
    # - It doesn't limit file size.
    if self._max_file_size_kb:  # logging to file is disabled for tests
      if not os.path.exists(LoggerFactory._LOG_DIR):
        os.mkdir(LoggerFactory._LOG_DIR)
      file_handler = logging.handlers.RotatingFileHandler(
          LoggerFactory._LOG_DIR + name + LoggerFactory._LOG_EXT,
          maxBytes = self._max_file_size_kb * 1024,
          backupCount = self._backup_count)
      file_handler.setFormatter(logging.Formatter(log_format))
      logger.addHandler(file_handler)
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(logging.Formatter(log_format))
    logger.addHandler(console_handler)
    LoggerFactory._TOPLEVEL_LOGGERS.add(name)
    return logger


LOGGER_FACTORY = LoggerFactory()
