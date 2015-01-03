import subprocess
import xml.dom.minidom
import traceback

import common
import log


class HuaweiStatus:
  """Provides status of the Huawei 3G stick, such as signal strength."""

  # Values to read from the Huawei stick: Huawei API name -> Huawei key -> our key
  _QUERY = {'status': {'CurrentNetworkType': 'nwtype',
                       'SignalStrength': 'strength'},
            'traffic-statistics': {'TotalUpload': 'upload',
                                   'TotalDownload': 'download'}}

  # Codes used by the Huawei stick to indicate network types.
  _NW_TYPES = {3: '2G', 4: '3G', 7: '3G+'}

  def __init__(self):
    self._log = log.get_logger('ipa.link')

  def get_sample(self):
    # TODO: It's a little awkward that we need to start a subprocess synchronously.
    sample = self._query_all_apis()
    if sample:
      # Map numeric network type to string.
      sample['nwtype'] = HuaweiStatus._NW_TYPES.get(sample['nwtype'], sample['nwtype'])
      sample['ts'] = common.timestamp()
    return 'link', sample

  def _query_all_apis(self):
    '''Query APIs and return values as per _QUERY. Returns None on failure.'''
    sample = {}
    try:
      for api_name, names in HuaweiStatus._QUERY.iteritems():
        dom = xml.dom.minidom.parseString(self._query_api(api_name))
        for name, key in names.iteritems():
          # All values are integers.
          sample[key] = int(self._get_value(dom, name))
      return sample
    except Exception:  # Catch all errors including parsing.
      self._log.error('failed to get Huawei 3G stick status: %s' % traceback.format_exc())
      return None

  def _query_api(self, name):
    return subprocess.check_output(['curl', '-s', '-S', 'http://hi.link/api/monitoring/' + name])

  def _get_value(self, dom, name):
    return dom.getElementsByTagName(name)[0].childNodes[0].data
