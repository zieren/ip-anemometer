#!/bin/bash

# Maximum size per log file.
LOG_FILE_ROLLOVER_SIZE=1048576  # 1 MB

# Number of rolled over log files to keep.
NUM_BACKUP_LOG_FILES=5

LOG_FILENAME="$(basename "$0").log"

function format_date() {
  date "+%Y%m%d-%H%M%S"
}

function epoch_seconds() {
  date "+%s"
}

function log() {
  local MESSAGE="$(format_date) $1"
  echo $MESSAGE
  echo $MESSAGE >> $LOG_FILENAME
  local SIZE=$(stat --format=%s $LOG_FILENAME)
  if [ $SIZE -gt $LOG_FILE_ROLLOVER_SIZE ]; then
    mv $LOG_FILENAME ${LOG_FILENAME}-$(format_date)
    prune $NUM_BACKUP_LOG_FILES $(ls_logs)
  fi
}

# Delete all but the first N files/dirs from the specified set.
# Usage: prune N FILES...
function prune() {
  local INDEX=0
  local NUM_TO_KEEP=$1
  shift
  local FILE
  for FILE in $*; do
    let "INDEX=$INDEX + 1"
    if [ $INDEX -gt $NUM_TO_KEEP ]; then
      rm -rf $FILE
      log "removed $FILE"
    fi
  done
}
