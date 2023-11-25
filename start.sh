#!/bin/bash

set -x

dpkg -l

export SQLITE_LOG_DB_FILE="/tmp/sqlitelog.db"

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export FIXED_THREAD_POOL=2
export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20180821/files/master/useragent.txt)
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/ /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}" >VERSION.txt
echo "Apache" >>VERSION.txt
apachectl -V | head -n 1 >>VERSION.txt
echo -e "PHP" >>VERSION.txt
php --version | head -n 1 >>VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
&& sleep 1s \
&& curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &

while true; do sleep 840s && ps aux && curl -sS -A "keep online" -u ${BASIC_USER}:${BASIC_PASSWORD} https://${RENDER_EXTERNAL_HOSTNAME}/; done &

. /etc/apache2/envvars
exec /usr/sbin/apache2 -DFOREGROUND
