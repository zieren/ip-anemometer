import unittest

import wind_revolutions
import wind_stats


SEC = 1000
MIN = 60 * SEC
TS_0 = 1000000000000
EDGES_PER_REV = 4
HSF = 3.013
LSF = 1.761
MAX_ROTATION = 10 * SEC  # must be < MIN above

class Test(unittest.TestCase):

  def test_Revolutions(self):
    edges_per_rev = 4
    revs = wind_revolutions.Revolutions(edges_per_rev)

    # Add N-1 edges.
    for i in range(edges_per_rev - 1):
      self.assertEqual(revs._edges, i)
      r = revs.get_and_reset()
      self.assertEqual(r, [])
      revs.add_edge(-1)

    # Add final edge.
    revs.add_edge(-1)
    self.assertEqual(revs._edges, 0)  # back to zero
    r = revs.get_and_reset()
    self.assertEqual(len(r), 1)
    self.assertGreater(r[0], 0)

  def newWindStats(self):
    return wind_stats.WindStats(HSF, LSF, MAX_ROTATION, TS_0)

  def test_WindStats(self):
    stats = self.newWindStats()

    # Nothing for a minute -> all zero.
    ts = TS_0 + MIN
    start_ts = TS_0
    end_ts = ts - MAX_ROTATION
    self.expectStats(stats.get_stats_and_reset(ts),
                     0, 0, end_ts, {0: 1.0}, start_ts, end_ts)
    # Another minute -> all zero.
    ts += MIN
    start_ts = end_ts
    end_ts = ts - MAX_ROTATION
    self.expectStats(stats.get_stats_and_reset(ts),
                     0, 0, end_ts, {0: 1.0}, start_ts, end_ts)
    # 10 one-second revolutions in a minute.
    for i in range(11):  # first revolution was inf long
      stats.next_timestamp(ts + i * SEC)
    max_ts = ts + 10 * SEC  # first at ts + 0 was inf long, all others are identical
    ts += MIN
    start_ts = end_ts
    end_ts = ts - MAX_ROTATION
    kmh_1 = stats.compute_kmh(SEC)
    avg_kmh = kmh_1 / 6.0
    self.expectStats(stats.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, {int(kmh_1): 1 / 6.0, 0: 5 / 6.0}, start_ts, end_ts)
    # One minute of continuous one-second revolutions.
    for i in range(60):
      stats.next_timestamp(ts + i * SEC)
    max_ts = ts + 59 * SEC
    ts += MIN
    start_ts = end_ts
    end_ts = ts - SEC
    # Covers MAX_ROTATION from last window with 0 km/h and up to ts - SEC.
    total_duration = float(MAX_ROTATION + 59 * SEC)
    avg_kmh = kmh_1 * 59 * SEC / total_duration
    histogram = {int(kmh_1): 59 * SEC / total_duration,
                 0: MAX_ROTATION / total_duration}
    self.expectStats(stats.get_stats_and_reset(ts),
                     avg_kmh, kmh_1, max_ts, histogram, start_ts, end_ts)
    # A minute of continuous half-second revolutions.
    for i in range(120):
      stats.next_timestamp(ts + i * SEC / 2)
    max_ts = ts + 119 * SEC / 2
    ts += MIN
    start_ts = end_ts
    end_ts = ts - SEC / 2
    # Plus one second from last window, minus half a second in current window.
    total_duration = float(MIN + SEC - SEC / 2)
    kmh_05 = stats.compute_kmh(SEC / 2)
    self.assertNotEqual(int(kmh_1), int(kmh_05))
    avg_kmh = (kmh_05 * 59.5 * SEC + kmh_1 * SEC) / total_duration
    histogram = {int(kmh_05): 59.5 * SEC / total_duration,
                 int(kmh_1): 1 * SEC / total_duration}
    self.expectStats(stats.get_stats_and_reset(ts),
                     avg_kmh, kmh_05, max_ts, histogram, start_ts, end_ts)

  def test_WindStats_noRevsFastUpdate(self):
    stats = self.newWindStats()
    ts = TS_0 + MAX_ROTATION - 1000L
    self.assertEqual(stats.get_stats_and_reset(ts), None)

  def test_WindStats_firstTimestampEqualsCreationTimestamp(self):
    stats = self.newWindStats()
    stats.next_timestamp(TS_0)
    stats.next_timestamp(TS_0 + SEC)

  def test_WindStats_startTimestampEqualsPreviousTimestamp(self):
    stats = self.newWindStats()
    for i in range(60):
      stats.next_timestamp(TS_0 + i * SEC)
    stats.get_stats_and_reset(TS_0 + MIN)
    self.assertEqual(stats.get_stats_and_reset(TS_0 + MIN + SEC), None)

  def test_WindStats_sameTimestampTwice(self):
    stats = self.newWindStats()
    stats.next_timestamp(TS_0 + SEC)
    stats.next_timestamp(TS_0 + SEC)

  def expectStats(self, stats, avg_kmh, max_kmh, max_timestamp, histogram, start_timestamp,
                  end_timestamp):
    self.assertAlmostEqual(stats['avg'], avg_kmh)
    self.assertEqual(stats['max'], max_kmh)
    self.assertEqual(stats['max_ts'], max_timestamp)
    self.assertEqual(stats['hist'], histogram)
    self.assertEqual(stats['start_ts'], start_timestamp)
    self.assertEqual(stats['end_ts'], end_timestamp)
