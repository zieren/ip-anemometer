import unittest

import config

class Test(unittest.TestCase):

  def test_ParseFile(self):
    c = config.Config()
    self.assertEqual(c.WIND_INPUT_PIN(), 42)
