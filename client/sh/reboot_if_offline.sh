#!/bin/bash

# Reboot if we are not online after some time. This is useful because
# occasionally the Huawei 3G stick does not show up as eth1 (possibly a flaky
# USB implementation on the Pi).

cd "$(dirname "$0")"
source lib.sh

# Ping an IP because the Huawei stick DNS-redirects to its own UI.
PING_IP=8.8.8.8  # Google public DNS server
SLEEP_TIME=5m  # time to sleep before checking, in case we run @reboot

sleep $SLEEP_TIME
STRATUM=$(ntpq -c "rv 0 stratum" | sed -r s/.*=//)
log "slept $SLEEP_TIME, stratum=$STRATUM, now running ping"
ping -c 3 -q $PING_IP &> /dev/null
if [ "$?" != "0" ]; then
  log "offline - rebooting"
  # This uses the Huawei API. For other sticks it will fail.
  log "$(curl -s -S http://hi.link/api/monitoring/status)"
  log "$(ifconfig)"
  reboot
fi
