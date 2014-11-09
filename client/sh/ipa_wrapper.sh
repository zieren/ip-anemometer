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

if [ "$1" == "" ]; then
  echo "Specify IPA web server base URL, e.g. http://server.com/ipa"
  echo "If authentication is required, use http://user:pass@server.com/ipa"
  exit 1
fi

DOWNLOAD_URL=$1/dl.php

# Internal variables.
VERSION=0.0.0
TIME_BARRIER=0  # epoch seconds
DL_FILENAME=ipa-update.zip
MD5=n/a

function set_time_barrier() {
  let "TIME_BARRIER=$(epoch_seconds) + $RUN_RETRY_SECONDS"
}

function wait_time_barrier() {
  local SECONDS
  let "SECONDS=$TIME_BARRIER - $(epoch_seconds)"
  if [ $SECONDS -gt 0 ]; then
    log "waiting $SECONDS seconds before restarting main program"
    sleep $SECONDS
  fi
}

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

log "IP anemometer wrapper $VERSION - (c) Joerg Zieren - http://zieren.de - GNU GPL v3"

./await_clock_sync.sh

while true; do
  rm -f $DL_FILENAME
  log "checking for new client (current: $MD5)"
  curl -sS "${DOWNLOAD_URL}?md5=${MD5}" -o $DL_FILENAME || (sleep_dl_retry ; continue)
	# If there is no update, the server sends an empty response and curl produces no file.
	if [ -f $DL_FILENAME ]; then  # install new archive
	  MD5=$(md5sum  $DL_FILENAME | cut -d ' ' -f 1)
    log "client downloaded (new: $MD5)"
	  DIR=ipa-$(format_date)
	  unzip -qq -d $DIR $DL_FILENAME
    if [ $? -ne 0 ]; then
      log "unzip failed"
      rm -rf $DIR
      sleep_dl_retry
      continue
    fi
	  chmod a+x $DIR/*.py $DIR/*.sh
	  ln -sfn $DIR current
	  rm $DL_FILENAME
	  log "client installed in $DIR"
    prune $NUM_DIRS_TO_KEEP $(ls_ipa_dirs)
  else  # continue with current version
    log "no new client available"
    wait_time_barrier  # throttle restarts of the same binary
  fi
  set_time_barrier
  log "starting main program"
  cd current
  sudo python main.py
  RETVAL=$?
  cd ..
  log "main program returned exit code $RETVAL"
  case $RETVAL in
    0)  # no error - restart
      continue
      ;;
    100)  # shutdown
      log "shutting down"
      sudo shutdown -h now
      ;;
    101)  # reboot
      log "rebooting"
      sudo reboot
      ;;
    *)  # error
      continue
      ;;
  esac
done
