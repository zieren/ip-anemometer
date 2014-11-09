#!/bin/bash

LOGFILE=online.log
# Ping an IP because the Huawei stick DNS-redirects to its own UI.
PING_IP=8.8.8.8
INTERVAL=30s

function formatDate() {
  date "+%Y%m%d-%H%M%S"
}

echo "$(formatDate) starting" >> $LOGFILE
FAILURES=
while true; do
  sleep $INTERVAL
  ping -c 3 -q $PING_IP &> /dev/null
  if [ "$?" != "0" ]; then
    FAILURES="x$FAILURES"
    echo "$(formatDate) offline" >> $LOGFILE
    ./huawei_status.sh &>> $LOGFILE

    if [ "$FAILURES" == "xxx" ]; then
      echo "rebooting..." >> $LOGFILE
      reboot
    fi
  fi
done
