import subprocess
import xml.dom.minidom

import common
import C  # TODO: Break dependency of K on C
import K


class HuaweiStatus:
  """Provides status of the Huawei 3G stick, such as signal strength."""

  # Values to read from the Huawei stick: Huawei API name -> Huawei key -> our key
  QUERY = {'status': {'CurrentNetworkType': K.LINK_NW_TYPE_KEY,
                      'SignalStrength': K.LINK_STRENGTH_KEY},
           'traffic-statistics': {'TotalUpload': K.LINK_UPLOAD_KEY,
                                  'TotalDownload': K.LINK_DOWNLOAD_KEY}}

  def __init__(self):
    self._log = K.get_logger(K.LOG_NAME_LINK)

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    sample = self._query_all_apis()
    if not sample:
      return K.LINK_KEY, None
    sample[K.TIMESTAMP_KEY] = common.timestamp()
    return K.LINK_KEY, sample

  def _query_all_apis(self):
    sample = {}
    try:
      for api_name, names in HuaweiStatus.QUERY.iteritems():
        dom = xml.dom.minidom.parseString(self._query_api(api_name))
        for name, key in names.iteritems():
          sample[key] = self._get_value(dom, name)
      return sample
    except Exception as e:  # Catch all errors including parsing.
      self._log.error('failed to get Huawei 3G stick status: %s' % str(e))
      return None

  def _query_api(self, name):
    return subprocess.check_output(['curl', '-s', '-S', 'http://hi.link/api/monitoring/' + name])

  def _get_value(self, dom, name):
    return dom.getElementsByTagName(name)[0].childNodes[0].data
