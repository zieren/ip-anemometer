#!/bin/bash

CONNECT=1
if [ "$1" == "0" ]; then
  CONNECT=0
fi

XML='<?xml version="1.0" encoding="UTF-8"?><request><Action>'$CONNECT'</Action></request>'
echo "$XML" \
    | curl -X POST -d @- -s -S http://hi.link/api/dialup/dial --header "Content-Type:text/xml"
