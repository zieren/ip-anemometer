from optparse import OptionParser
import re


class Args:
  """Parses and provides command line arguments.

  Initialized in parse().
  """

  def _create_parser(self):  # for mocking OptionParser in test
    return OptionParser(usage=
        'usage: %prog SERVER_URL\n'
        '\n'
        'SERVER_URL:\n'
        '  The URL of the "client" directory on the server, optionally\n'
        '  including username and password. Examples:\n'
        '  http://server.com/ipa/client\n'
        '  http://user:pass@server.com/ipa/client')

  def parse(self):
    (_options_unused, args) = self._create_parser().parse_args()
    if len(args) != 1:
      raise ValueError('Please specify SERVER_URL (see --help)')
    # Process arguments.
    m = re.match('(?:http://)?(([^:]+):(.+)@)?([^@]+)', args[0])
    if not m:
      raise ValueError('Invalid SERVER_URL: %s' % args[0])
    self._server_url = 'http://' + m.group(4)
    if self._server_url[-1] != '/':
      self._server_url += '/'
    self._server_username = m.group(2) if m.group(1) else None
    self._server_password = m.group(3) if m.group(1) else None

  def server_url(self):
    return self._server_url

  def server_username(self):
    return self._server_username

  def server_password(self):
    return self._server_password


ARGS = Args()  # delay parse() to allow testing
