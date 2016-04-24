import types
import unittest

import common
import huawei_status
from logger import LOGGER_FACTORY


FAKE_TIMESTAMP = 1234567890123L


class Test(unittest.TestCase):

  def setUp(self):
    LOGGER_FACTORY.initialize('DEBUG')

  def test_HuaweiStatus(self):
    huawei = huawei_status.HuaweiStatus()
    huawei._query_api = types.MethodType(huawei_fake_query_api, huawei)
    common.timestamp = fake_common_timestamp
    sample = huawei.get_sample()
    self.assertEqual(sample, ('link', {'nwtype': '3G+',
                                       'strength': 97,
                                       'upload': 1010629397,
                                       'download': 414422886,
                                       'ts': FAKE_TIMESTAMP}))

def huawei_fake_query_api(self, name):
  return open('%s.xml' % name).read()

def fake_common_timestamp():
  return FAKE_TIMESTAMP
