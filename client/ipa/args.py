from optparse import OptionParser
import re


class Args:
  """Parses and provides command line arguments.

  Initialized in parse().
  """

  def _create_parser(self):  # for mocking OptionParser in test
    return OptionParser(usage=
        'usage: %prog SERVER_URL ARCHIVE_MD5\n'
        '\n'
        'SERVER_URL:\n'
        '  The URL of the "client" directory on the server, optionally\n'
        '  including username and password. Examples:\n'
        '  http://server.com/ipa/client\n'
        '  http://user:pass@server.com/ipa/client\n'
        '\n'
        'ARCHIVE_MD5:\n'
        '  MD5 of the archive from which the client was unpacked (for update check).')

  def parse(self):
    (_options_unused, args) = self._create_parser().parse_args()
    if len(args) != 2:
      raise ValueError('Please specify SERVER_URL and ARCHIVE_MD5 (see --help)')
    # Process arguments.
    self._archive_md5 = args[1]
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

  def archive_md5(self):
    return self._archive_md5


ARGS = Args()  # delay parse() to allow testing
