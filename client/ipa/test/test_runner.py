import unittest

import stats
import C


class Test(unittest.TestCase):

  def test_Stats(self):
    s = stats.Stats()
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
