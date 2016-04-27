import unittest

from args import ARGS


class MockOptionParser:
  def __init__(self, parse_args_retval):
    self._parse_args_retval = parse_args_retval
  def parse_args(self):
    return self._parse_args_retval
  def create_parser(self):
    return self


class Test(unittest.TestCase):

  def _doTest(self, input_url, expected_url, expected_username=None, expected_password=None):
    parser = MockOptionParser((None, [input_url]))
    ARGS._create_parser = parser.create_parser
    ARGS.parse()
    self.assertEqual(ARGS.server_url(), expected_url)
    self.assertEqual(ARGS.server_username(), expected_username)
    self.assertEqual(ARGS.server_password(), expected_password)

  def test_ParseArgs(self):
    self._doTest('foo', 'http://foo/')
    self._doTest('http://a.b/c', 'http://a.b/c/')
    self._doTest('user:pass@a.b/c', 'http://a.b/c/', 'user', 'pass')
    self._doTest('http://user:pass@a.b/c', 'http://a.b/c/', 'user', 'pass')
    self._doTest('http://user@a.b:p@ss@a.b/c', 'http://a.b/c/', 'user@a.b', 'p@ss')
