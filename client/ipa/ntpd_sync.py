#!/usr/bin/env python

# Checks the current stratum and restarts ntpd if the clock is not synced.


import raspisys
import subprocess

import K


_ACCEPTABLE_STRATUM = 15  # The worst, but still synced.
_NTPD_RESTART_CMD = ['/etc/init.d/ntp', 'restart']

if __name__ == "__main__":
  log = K.get_logger(K.LOG_NAME_NTPDSYNC)
  stratum = raspisys.get_stratum()
  log.info('stratum: %d (acceptable: %d)' % (stratum, _ACCEPTABLE_STRATUM))
  if stratum > _ACCEPTABLE_STRATUM:
    retcode = subprocess.call(_NTPD_RESTART_CMD)
    log.info('ntpd restart %s (retval: %d)' % ('FAILED' if retcode else 'OK', retcode))
    quit(retcode)
  log.info('ntpd restart not required')
