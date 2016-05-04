#!/bin/bash

PID=$(ps -ef | egrep "\bsudo python main.py\b" | awk '{print $2}')
if [ -z "$PID" ]; then
  echo "IPA not running."
  exit 1
else
  echo "Killing $PID..."
fi
sudo kill $PID
