#!/bin/bash

set -x

apt-get update
apt-get -y upgrade

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20180821/files/master/useragent.txt)
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/ /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

cat /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}" >VERSION.txt
echo "Apache" >>VERSION.txt
apachectl -V | head -n 1 >>VERSION.txt
echo -e "PHP" >>VERSION.txt
php --version | head -n 1 >>VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt
rm -f /usr/src/app/*.java

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
&& sleep 1s \
&& curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &

. /etc/apache2/envvars
exec /usr/sbin/apache2 -DFOREGROUND
