#!/bin/bash

cd "$(dirname "$0")"
source lib.sh

# For information on NTP (e.g. stratum) see http://en.wikipedia.org/wiki/Network_Time_Protocol.

ACCEPTABLE_STRATUM=8
CHECK_INTERVAL_SECONDS=5
RESTART_AFTER_SECONDS=60
TIMEOUT_SECONDS=180

function set_restart_epoch_seconds() {
  let "RESTART_EPOCH_SECONDS=$(epoch_seconds) + $RESTART_AFTER_SECONDS"
}

function set_timeout_epoch_seconds() {
  let "TIMEOUT_EPOCH_SECONDS=$(epoch_seconds) + $TIMEOUT_SECONDS"
}

STRATUM=16
set_restart_epoch_seconds
set_timeout_epoch_seconds
log "waiting for clock to sync (stratum must be <= $ACCEPTABLE_STRATUM)"
while [ $(epoch_seconds) -lt $TIMEOUT_EPOCH_SECONDS ]; do
  STRATUM=$(ntpq -c "rv 0 stratum" | sed -r s/.*=//)
  if [ $STRATUM -le $ACCEPTABLE_STRATUM ]; then
    log "clock is synced (stratum: $STRATUM)"
    exit 0
  fi
  if [ $(epoch_seconds) -gt $RESTART_EPOCH_SECONDS ]; then
    log "restarting ntpd"
    sudo /etc/init.d/ntp restart
    set_restart_epoch_seconds
  fi
  sleep $CHECK_INTERVAL_SECONDS
done

log "failed to sync clock"
exit 1
