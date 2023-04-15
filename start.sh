#!/bin/bash

set -x

export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20180821/files/master/useragent.txt)
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

cat /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

. /etc/apache2/envvars
exec /usr/sbin/apache2 -DFOREGROUND
