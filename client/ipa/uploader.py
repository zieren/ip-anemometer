import base64
import bz2
import threading
import time

import C
import K
import urllib2
import json

class Status:
  OK, HTTP_CODE_NOT_OK, EXCEPTION, INVALID_JSON, INVALID_RESPONSE, RESPONSE_STATUS_NOT_OK = range(6)

class Uploader(threading.Thread):
  """Periodically uploads accumulated measurements."""

  def __init__(self, command_queue, termination_event):
    """Commands received from the server will be put into the command_queue."""
    threading.Thread.__init__(self)
    self._log = K.get_logger(K.LOG_NAME_UPLOADER)
    self._main_cq = command_queue
    self._termination_event = termination_event
    self._queue = {}
    self._last_status = Status.OK
    # To judge the quality of the uplink, the client transmits the number of failed upload attempts
    # in the metadata. Only counts connection errors, not server errors.
    self._failed_uploads = 0
    self._sources = []  # holds (data_source, buffering)
    # The uploader is itself a data source (number of failed attempts, and maybe more, some day).
    self.add_data_source(self, False)

  def add_data_source(self, data_source, buffering):
    """A data source provides a get_values() method that returns a tuple of a key and some type of
    measurement value. The value type is arbitrary (the server has to handle it). The "buffering"
    flag controls handling of existing values when polling new values (e.g. when an upload has
    failed): If True, the uploader maintains a list and new values are appended, if False it stores
    only a single value and overwrites it with new a value (so that the value appears at most once
    per upload)."""
    self._sources.append((data_source, buffering))

  def _poll_data_sources(self):
    for data_source, buffering in self._sources:
      type_key, values = data_source.get_values()
      if buffering:
        self._queue.setdefault(type_key, []).append(values)
      else:  # overwrite
        self._queue[type_key] = values

  def run(self):
    self._log.info('starting (upload interval: %ds)' % C.UPLOAD_INTERVAL_SECONDS())
    wait_seconds = C.UPLOAD_INTERVAL_SECONDS()
    while True:
      if self._termination_event.wait(wait_seconds):  # returns immediately if <= 0
        return
      start_time = time.time()
      self._poll_data_sources()
      if self._upload():
        self._queue = {}
      wait_seconds = C.UPLOAD_INTERVAL_SECONDS() - (time.time() - start_time)

  def get_values(self):
    return K.UPLOAD_KEY, {K.FAILED_UPLOADS_KEY: self._failed_uploads}

  def _log_error_or_suppress(self, status, message):
    if not C.LOGGING_SUPPRESS_REPEATED_ERRORS() or self._last_status != status:
      self._log.error(message)
    self._last_status = status

  def _upload(self):
    """Returns True if values were uploaded, False if (likely) not."""
    data_json = json.dumps(self._queue)
    data_bz2 = bz2.compress(data_json)
    # TODO: Limit upload size as per config (e.g. 100kB for a GPRS link). When size is exceeded,
    # purge queue in a suitable way (discard oldest half and retry? discard all?).
    self._failed_uploads += 1  # assume failure; will reset to zero on success
    try:
      request = urllib2.Request(C.UPLOAD_URL(), data_bz2,
          {'Content-Type': 'application/octet-stream'})
      if C.UPLOAD_USERNAME() or C.UPLOAD_PASSWORD():
        auth_string = base64.encodestring('%s:%s' % (C.UPLOAD_USERNAME(),
                                                     C.UPLOAD_PASSWORD())).replace('\n', '')
        request.add_header('Authorization', 'Basic %s' % auth_string)
      self._log.debug('connecting to server...')
      response = urllib2.urlopen(request, timeout = C.TIMEOUT_HTTP_REQUEST_SECONDS())
      if response.getcode() != 200:
        self._log_error_or_suppress(Status.HTTP_CODE_NOT_OK,
            'failed to post data: HTTP status code %d' % response.getcode())
        return False
      self._failed_uploads = 0
    except Exception as e:
      self._log_error_or_suppress(Status.EXCEPTION, 'failed to post data: %s' % e)
      return False
    response_content = response.read()
    try:
      response_dict = json.loads(response_content)
    except Exception as e:
      # This might be the 3G stick's error/"no network" (or rather: "no javascript" :-) page.
      self._log_error_or_suppress(Status.INVALID_JSON,
          'failed to parse server response: %s\nserver response begins with: "%s"'
          % (e, response_content[:1000]))  # TODO: Configurable?
      return False
    if response_dict.setdefault(
        K.RESPONSE_STATUS, K.RESPONSE_STATUS_UNKNOWN) != K.RESPONSE_STATUS_OK:
      self._log_error_or_suppress(Status.RESPONSE_STATUS_NOT_OK,
          'upload failed; status: %s' % response_dict[K.RESPONSE_STATUS])
      return False
    self._last_status = Status.OK
    self._log.debug('upload OK (%d bytes) - response: %s' % (len(data_bz2), response_content))
    # Add commands to main command queue.
    for k, v in response_dict.items():
      if k != K.RESPONSE_STATUS:
        self._main_cq.put((k, v))
    return True
