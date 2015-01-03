import types
import unittest

import common
import huawei_status
import K
import wind_sensor
import wind_stats
from wind_stats import WindKey


SEC = 1000
MIN = 60 * SEC
TS_0 = 1000000000000
FAKE_TIMESTAMP = 1234567890123L


class Test(unittest.TestCase):

  def test_Meta(self):
    # Tests rely on this.
    self.assertGreater(MIN, wind_stats._MAX_ROTATION)

  def test_Revolutions(self):
    s = wind_sensor.Revolutions()
    edges = 4

    # Add N-1 edges.
    for i in range(edges - 1):
      self.assertEqual(s._edges, i)
      revs = s.get_and_reset()
      self.assertEqual(revs, [])
      s.add_edge(-1)

    # Add final edge.
    s.add_edge(-1)
    self.assertEqual(s._edges, 0)  # back to zero
    revs = s.get_and_reset()
    self.assertEqual(len(revs), 1)
    self.assertGreater(revs[0], 0)

  def test_WindStatsCalculator(self):
    calc = wind_stats.WindStatsCalculator(TS_0)

    # Nothing for a minute -> all zero.
    ts = TS_0 + MIN
    start_ts = TS_0
    end_ts = ts - wind_stats._MAX_ROTATION
    self.expectStats(calc.get_stats_and_reset(ts),
                     0, 0, end_ts, {0: 1.0}, start_ts, end_ts)
    # Another minute -> all zero.
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._MAX_ROTATION
    self.expectStats(calc.get_stats_and_reset(ts),
                     0, 0, end_ts, {0: 1.0}, start_ts, end_ts)
    # 10 one-second revolutions in a minute.
    for i in range(11):  # first revolution was inf long
      calc.next_timestamp(ts + i * SEC)
    max_ts = ts + 10 * SEC  # first at ts + 0 was inf long, all others are identical
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._MAX_ROTATION
    kmh_1 = wind_stats.compute_kmh(SEC)
    avg_kmh = kmh_1 / 6.0
    self.expectStats(calc.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, {int(kmh_1): 1 / 6.0, 0: 5 / 6.0}, start_ts, end_ts)
    # One minute of continuous one-second revolutions.
    for i in range(60):
      calc.next_timestamp(ts + i * SEC)
    max_ts = ts + 59 * SEC
    ts += MIN
    start_ts = end_ts
    end_ts = ts - SEC
    # Covers wind_stats._MAX_ROTATION from last window with 0 km/h and up to ts - SEC.
    total_duration = float(wind_stats._MAX_ROTATION + 59 * SEC)
    avg_kmh = kmh_1 * 59 * SEC / total_duration
    histogram = {int(kmh_1): 59 * SEC / total_duration,
                 0: wind_stats._MAX_ROTATION / total_duration}
    self.expectStats(calc.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, histogram, start_ts, end_ts)
    # A minute of continuous half-second revolutions.
    for i in range(120):
      calc.next_timestamp(ts + i * SEC / 2)
    max_ts = ts + 119 * SEC / 2
    ts += MIN
    start_ts = end_ts
    end_ts = ts - SEC / 2
    # Plus one second from last window, minus half a second in current window.
    total_duration = float(MIN + SEC - SEC / 2)
    kmh_05 = wind_stats.compute_kmh(SEC / 2)
    self.assertNotEqual(int(kmh_1), int(kmh_05))
    avg_kmh = (kmh_05 * 59.5 * SEC + kmh_1 * SEC) / total_duration
    histogram = {int(kmh_05): 59.5 * SEC / total_duration,
                 int(kmh_1): 1 * SEC / total_duration}
    self.expectStats(calc.get_stats_and_reset(ts),
                     avg_kmh, kmh_05, max_ts, histogram, start_ts, end_ts)

  def test_WindStatsCalculator_noRevsFastUpdate(self):
    calc = wind_stats.WindStatsCalculator(TS_0)
    ts = TS_0 + wind_stats._MAX_ROTATION - 1000L
    self.assertEqual(calc.get_stats_and_reset(ts), None)

  def test_WindStatsCalculator_firstTimestampEqualsCreationTimestamp(self):
    calc = wind_stats.WindStatsCalculator(TS_0)
    calc.next_timestamp(TS_0)
    calc.next_timestamp(TS_0 + SEC)

  def test_WindStatsCalculator_startTimestampEqualsPreviousTimestamp(self):
    calc = wind_stats.WindStatsCalculator(TS_0)
    for i in range(60):
      calc.next_timestamp(TS_0 + i * SEC)
    calc.get_stats_and_reset(TS_0 + MIN)
    self.assertEqual(calc.get_stats_and_reset(TS_0 + MIN + SEC), None)

  def test_WindStatsCalculator_sameTimestampTwice(self):
    calc = wind_stats.WindStatsCalculator(TS_0)
    calc.next_timestamp(TS_0 + SEC)
    calc.next_timestamp(TS_0 + SEC)

  def expectStats(self, stats, avg_kmh, max_kmh, max_timestamp, histogram, start_timestamp,
                  end_timestamp):
    self.assertAlmostEqual(stats[WindKey.AVG_KMH], avg_kmh)
    self.assertEqual(stats[WindKey.MAX_KMH], max_kmh)
    self.assertEqual(stats[WindKey.MAX_TIMESTAMP], max_timestamp)
    self.assertEqual(stats[WindKey.HISTOGRAM], histogram)
    self.assertEqual(stats[WindKey.START_TIMESTAMP], start_timestamp)
    self.assertEqual(stats[WindKey.END_TIMESTAMP], end_timestamp)

  def test_HuaweiStatus(self):
    huawei = huawei_status.HuaweiStatus()
    huawei._query_api = types.MethodType(huaweiFakeQueryApi, huawei)
    common.timestamp = fakeCommonTimestamp
    sample = huawei.get_sample()
    self.assertEqual(sample, (K.LINK_KEY, {K.LINK_NW_TYPE_KEY: '3G+',
                                           K.LINK_STRENGTH_KEY: 97,
                                           K.LINK_UPLOAD_KEY: 1010629397,
                                           K.LINK_DOWNLOAD_KEY: 414422886,
                                           K.TIMESTAMP_KEY: FAKE_TIMESTAMP}))

def huaweiFakeQueryApi(self, name):
  return open('test/%s.xml' % name).read()

def fakeCommonTimestamp():
  return FAKE_TIMESTAMP