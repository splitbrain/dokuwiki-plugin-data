#!/usr/bin/sh
#
# This will install the required sqlite plugin on travis

# make sure this runs on travis only
if [ -z "$TRAVIS" ]; then
    echo 'This script is only intended to run on travis-ci.org build servers'
    exit 1
fi

# get the sqlite plugin which is required for this plugin
cd lib/plugins || exit 1
git clone https://github.com/cosmocode/sqlite.git sqlite
git clone https://github.com/splitbrain/dokuwiki-plugin-translation.git translation

