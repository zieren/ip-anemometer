#!/bin/bash

cd "$(dirname "$0")"
source lib.sh

# ========== begin optional configuration ==========

# Time to sleep after a failed client download (i.e. wait for link to come back
# up).
DL_RETRY_SECONDS=120

# Minimum time between restarts of the main program.
RUN_RETRY_SECONDS=120

# Number of old versions (i.e. directories) to keep.
NUM_DIRS_TO_KEEP=10

# ========== end optional configuration ==========

SERVER_URL="$1"

if [ -z "$SERVER_URL" ]; then
  echo "Specify the URL of the 'client' directory on your server, e.g. http://server.com/ipa/client"
  echo "If authentication is required, use http://user:pass@server.com/ipa/client"
  exit 1
fi

# Internal variables.
GREETING="IP anemometer wrapper 0.3.2 - (c) 2016 Joerg Zieren - http://zieren.de - GNU GPL v3"
DL_FILENAME=ipa-client.zip

function sleep_dl_retry() {
  log "sleeping $DL_RETRY_SECONDS seconds before retrying download"
  sleep $DL_RETRY_SECONDS
}

function ls_ipa_dirs() {
  ls -dr ipa-????????-??????
}

function ls_logs() {
  ls -r *.log*
}

log "$GREETING"

./await_clock_sync.sh

UPDATE_CLIENT=0
if [ ! -f current/main.py ]; then
  log "client not found, will download"
  UPDATE_CLIENT=1
fi
while true; do
  # Download client archive if no client exists (first run).
  if [ $UPDATE_CLIENT -ne 0 ]; then
    log "downloading client"
    rm -f "$DL_FILENAME"
    HTTP_CODE=$(curl -sS "$SERVER_URL/$DL_FILENAME" -o "$DL_FILENAME" -w "%{http_code}")
    RETVAL=$?
    if [ $RETVAL -ne 0 ]; then
      log "download failed, curl returned error code $RETVAL"
      # Maybe just a flaky link; wait and retry.
      sleep_dl_retry
      continue
    fi
    if [ $HTTP_CODE -ne 200 ]; then
      # This error is likely permanent.
      log "download failed, HTTP error code $HTTP_CODE (exiting)"
      exit
    fi
    log "client downloaded"
    DIR=ipa-$(format_date)
    unzip -qq -d $DIR $DL_FILENAME
    if [ $? -ne 0 ]; then
      # This error is likely permanent.
      log "unzip failed (exiting)"
      rm -rf $DIR
      exit
    fi
    UPDATE_CLIENT=0
    chmod a+x $(ls $DIR/*.py $DIR/*.sh 2> /dev/null)
    ln -sfn $DIR current
    rm $DL_FILENAME
    log "client installed in $DIR"
    prune $NUM_DIRS_TO_KEEP $(ls_ipa_dirs)
  fi
  log "starting client"
  cd current
  sudo python main.py "$SERVER_URL"
  RETVAL=$?
  cd ..
  log "client returned exit code $RETVAL"
  case $RETVAL in
    0)  # no error; exit
      log "exiting"
      exit
      ;;
    100)  # shutdown
      log "shutting down"
      sudo shutdown -h now
      ;;
    101)  # reboot
      log "rebooting"
      sudo reboot
      ;;
    102)  # update
      log "update available"
      UPDATE_CLIENT=1
      continue
      ;;
    103)  # restart
      log "restarting"
      continue
      ;;
    143)  # SIGTERM
      log "main application caught SIGTERM, exiting"
      exit
      ;;
    *)  # error
      log "restarting client"  # ... hoping that the error was transient
      continue
      ;;
  esac
done
