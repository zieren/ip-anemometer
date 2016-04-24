#!/bin/bash

sudo kill $(ps au | egrep 'ipa_wrapper|python' | grep -v grep | awk '{print $2}')
