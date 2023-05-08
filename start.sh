#!/bin/bash

set -x

apt-get update
apt-get -y upgrade

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev//urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20180821/files/master/useragent.txt)
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

cat /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

. /etc/apache2/envvars
exec /usr/sbin/apache2 -DFOREGROUND
