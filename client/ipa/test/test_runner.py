import unittest

import wind_sensor
import wind_stats
from wind_stats import Wind

import C


SEC = 1000
MIN = 60 * SEC
TS_0 = 1234567890123

class Test(unittest.TestCase):

  def test_Revolutions(self):
    s = wind_sensor.Revolutions()
    edges = C.WIND_EDGES_PER_REV()

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
    c = wind_stats.WindStatsCalculator(TS_0)
    # Nothing for a minute -> all zero.
    ts = TS_0 + MIN
    start_ts = TS_0
    end_ts = ts - wind_stats._NO_WIND_DURATION
    self.expectStats(c.get_stats_and_reset(ts),
                     0, 0, 0, {0: 1.0}, start_ts, end_ts)
    # Another minute -> all zero.
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._NO_WIND_DURATION
    self.expectStats(c.get_stats_and_reset(ts),
                     0, 0, 0, {0: 1.0}, start_ts, end_ts)
    # 10 one-second revolutions in a minute.
    for i in range(11):  # first revolution was inf long
      c.next_timestamp(ts + i * SEC)
    max_ts = ts + SEC  # first at ts + 0 was inf long, so max is only at ts + SEC
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._NO_WIND_DURATION
    kmh = wind_stats.compute_kmh(SEC)
    avg_kmh = kmh / 6.0
    self.expectStats(c.get_stats_and_reset(ts),
                     avg_kmh, kmh, max_ts, {int(kmh): 1 / 6.0, 0: 5 / 6.0}, start_ts, end_ts)

  def expectStats(self, stats, avg_kmh, max_kmh, max_timestamp, histogram, start_timestamp,
                  end_timestamp):
    self.assertEqual(stats[Wind.AVG_KMH], avg_kmh)
    self.assertEqual(stats[Wind.MAX_KMH], max_kmh)
    self.assertEqual(stats[Wind.MAX_TIMESTAMP], max_timestamp)
    self.assertEqual(stats[Wind.HISTOGRAM], histogram)
    self.assertEqual(stats[Wind.START_TIMESTAMP], start_timestamp)
    self.assertEqual(stats[Wind.END_TIMESTAMP], end_timestamp)
