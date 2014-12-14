#!/bin/bash

cd "$(dirname "$0")"
source lib.sh

FWD_PORT=2222
WAIT_SECONDS=60

if [ "$#" != "3" ]; then
  echo "Maintain a reverse ssh connection, forwarding port $FWD_PORT on the remote machine."
  echo "If the connection fails or is dropped, wait $WAIT_SECONDS seconds and retry."
  echo "Usage: $(basename $0) [remote_user@]remote_server port password"
  echo "Then, on the remote ssh server: ssh $USER@localhost -p $FWD_PORT"
  exit
fi

REMOTE_ADDRESS=$1
REMOTE_PORT=$2
PASSWORD=$3

# By default the tunnel never seems to timeout. This is bad because if the connection to the
# server has been established once, then the server disconnects (link down, or maybe it's a laptop
# that doesn't run 24/7), it could never be re-established. So it's important to set a timeout for
# the tunnel. Note that keepalive is handled transparently by ssh; it does not mean any payload data
# has to be sent through the tunnel at these intervals.
SERVER_ALIVE_INTERVAL=60
SERVER_ALIVE_COUNT_MAX=2

while true; do
  log "connecting..."
  sshpass -p "$PASSWORD" \
  ssh -o "ServerAliveInterval=$SERVER_ALIVE_INTERVAL" \
      -o "ServerAliveCountMax=$SERVER_ALIVE_COUNT_MAX" \
      -o "StrictHostKeyChecking=no" \
      -o "UserKnownHostsFile=/dev/null" \
      -o "ConnectTimeout=15" \
      -N -R $FWD_PORT:localhost:22 $REMOTE_ADDRESS -p $REMOTE_PORT
  SSH_RETVAL=$?
  log "ssh retval: $SSH_RETVAL"
  sleep $WAIT_SECONDS
done
