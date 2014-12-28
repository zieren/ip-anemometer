import unittest

import wind_sensor
import wind_stats
from wind_stats import WindKey

import C


SEC = 1000
MIN = 60 * SEC
TS_0 = 1000000000000

class Test(unittest.TestCase):

  def test_Meta(self):
    # Tests rely on this.
    self.assertGreater(MIN, wind_stats._NO_WIND_DURATION)

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
    calc = wind_stats.WindStatsCalculator(TS_0)

    # Nothing for a minute -> all zero.
    ts = TS_0 + MIN
    start_ts = TS_0
    end_ts = ts - wind_stats._NO_WIND_DURATION
    self.expectStats(calc.get_stats_and_reset(ts),
                     0, 0, 0, {0: 1.0}, start_ts, end_ts)
    # Another minute -> all zero.
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._NO_WIND_DURATION
    self.expectStats(calc.get_stats_and_reset(ts),
                     0, 0, 0, {0: 1.0}, start_ts, end_ts)
    # 10 one-second revolutions in a minute.
    for i in range(11):  # first revolution was inf long
      calc.next_timestamp(ts + i * SEC)
    max_ts = ts + SEC  # first at ts + 0 was inf long, so max is only at ts + SEC
    ts += MIN
    start_ts = end_ts
    end_ts = ts - wind_stats._NO_WIND_DURATION
    kmh_1 = wind_stats.compute_kmh(SEC)
    avg_kmh = kmh_1 / 6.0
    self.expectStats(calc.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, {int(kmh_1): 1 / 6.0, 0: 5 / 6.0}, start_ts, end_ts)
    # One minute of continuous one-second revolutions.
    for i in range(60):
      calc.next_timestamp(ts + i * SEC)
    max_ts = ts + SEC
    ts += MIN
    start_ts = end_ts
    end_ts = ts - SEC
    # Covers wind_stats._NO_WIND_DURATION from last window with 0 km/h and up to ts - SEC.
    total_duration = float(wind_stats._NO_WIND_DURATION + 59 * SEC)
    avg_kmh = kmh_1 * 59 * SEC / total_duration
    histogram = {int(kmh_1): 59 * SEC / total_duration,
                 0: wind_stats._NO_WIND_DURATION / total_duration}
    self.expectStats(calc.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, histogram, start_ts, end_ts)
    # A minute of continuous half-second revolutions.
    for i in range(120):
      calc.next_timestamp(ts + i * SEC / 2)
    max_ts = ts + SEC / 2
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
    ts = TS_0 + wind_stats._NO_WIND_DURATION - 1000L
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
