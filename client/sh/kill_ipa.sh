#!/bin/bash

PIDS=$(ps au | egrep 'ipa_wrapper|python' | grep -v grep | awk '{print $2}')
if [ -z "$PIDS" ]; then
  echo "IPA not running."
  exit 1
fi
sudo kill $PIDS
