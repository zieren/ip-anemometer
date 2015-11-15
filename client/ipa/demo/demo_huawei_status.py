import common
import huawei_status
from demo_util import random_value


class HuaweiStatus:

  def __init__(self):
    self._upload_mb = 1000
    self._download_mb = 100

  def get_sample(self):
    nwtype = int(random_value(3, 6))
    if nwtype == 5:
      nwtype = 7
    sample = {
        'nwtype': huawei_status.HuaweiStatus._NW_TYPES[nwtype],
        'strength': random_value(65, 85),
        'upload': self._upload_mb * 1024 * 1024,
        'download': self._download_mb * 1024 * 1024,
        'ts': common.timestamp()}
    self._upload_mb += 1
    self._download_mb += 1
    return 'link', sample
