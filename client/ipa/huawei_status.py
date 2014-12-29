import subprocess
import xml.dom.minidom

import common
import C  # TODO: Break dependency of K on C
import K


_COMMANDLINE = ['curl', '-s', '-S', 'http://hi.link/api/monitoring/status']


class HuaweiStatus:
  """Provides status of the Huawei 3G stick, such as signal strength."""

  # Values to read from the Huawei stick.
  NAMES = {K.LINK_STATUS_KEY: 'ConnectionStatus',
           K.LINK_STRENGTH_KEY: 'SignalStrength'}

  def __init__(self):
    self._log = K.get_logger(K.LOG_NAME_LINK)

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    status = self._get_status()
    if not status:
      return K.LINK_KEY, None
    status[K.TIMESTAMP_KEY] = common.timestamp()
    return K.LINK_KEY, status

  def _get_status(self):  # change this to provide more status
    try:
      return self._parse_status(subprocess.check_output(_COMMANDLINE))
    except Exception as e:  # Catch all errors including parsing.
      self._log.error('failed to get Huawei 3G stick status: %s' % str(e))

  def _parse_status(self, status_xml):
    dom = xml.dom.minidom.parseString(status_xml)
    status = {}
    for key, name in HuaweiStatus.NAMES.iteritems():
      status[key] = self._get_value(dom, name)
    return status

  def _get_value(self, dom, name):
    return dom.getElementsByTagName(name)[0].childNodes[0].data