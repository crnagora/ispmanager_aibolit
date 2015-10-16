#!/bin/bash

find /usr/local/ispmgr/var/.plugin_aibolit/*.lock -prune | while read f; do
cd /usr/local/ispmgr/var/.plugin_aibolit/
mv $f $f.finish
chmod 755 $f.finish
su -c "$f.finish"
rm $f.finish
done
