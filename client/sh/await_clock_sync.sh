#!/bin/bash

cd "$(dirname "$0")"
source lib.sh

# For information on NTP (e.g. stratum) see http://en.wikipedia.org/wiki/Network_Time_Protocol.

# Arbitrarily require a stratum of 8 or better.
ACCEPTABLE_STRATUM=8
CHECK_INTERVAL_SECONDS=5
# The clock might skip, so we count iterations instead.
RESTART_AFTER_CHECKS=10
TIMEOUT_AFTER_CHECKS=30

STRATUM=16
i=0
log "waiting for clock to sync (stratum must be <= $ACCEPTABLE_STRATUM)"
while [ $i -lt $TIMEOUT_AFTER_CHECKS ]; do
  STRATUM=$(ntpq -c "rv 0 stratum" | sed -r s/.*=//)
  log "stratum now: $STRATUM"
  if [ -n "$STRATUM" ] && [ "$STRATUM" -le $ACCEPTABLE_STRATUM ]; then
    log "clock is synced"
    exit 0
  fi
  let i="$i + 1"
  let DO_RESTART="$i % $RESTART_AFTER_CHECKS"
  if [ $DO_RESTART -eq 0 ]; then
    log "restarting ntpd"
    sudo /etc/init.d/ntp restart
  fi
  sleep $CHECK_INTERVAL_SECONDS
done

log "failed to sync clock"
exit 1
