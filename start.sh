#!/bin/bash

set -x

apt_result2cache() {
  apt-get -qq update
  curl -X POST -sS -H "Authorization: Bearer ${UPSTASH_REDIS_REST_TOKEN}" \
   -d "$(echo -n '["SET", "__KEY__", "__VALUE__", "EX", "86400"]' | \
    sed "s/__KEY__/APT_RESULT_${RENDER_EXTERNAL_HOSTNAME}/" | \
    sed "s/__VALUE__/$(date +'%Y-%m-%d %H:%M') $(apt-get -s upgrade | grep installed)/")" \
   "${UPSTASH_REDIS_REST_URL}"
  apt-get -s upgrade >/var/www/html/auth/apt_dry_run_result.txt
}

dpkg -l

export HOST_VERSION=$(cat /proc/version)
export GUEST_VERSION=$(grep "PRETTY_NAME" /etc/os-release | cut -c 13- | tr -d '"')
export PROCESSOR_NAME=$(grep "model name" /proc/cpuinfo | head -n 1 | cut -c 14-)
export APACHE_VERSION=$(apachectl -V | head -n 1)
export PHP_VERSION=$(php --version | head -n 1)
export JAVA_VERSION=$(java --version | head -n 1)

export SQLITE_LOG_DB_FILE="/tmp/sqlitelog.db"

# phpMyAdmin
export BLOWFISH_SECRET=$(tr -dc 'a-zA-Z0-9' </dev/urandom | fold -w 32 | head -n 1)

export FIXED_THREAD_POOL=2
export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20180821/files/master/useragent.txt)
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/ /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

# version
{ \
  echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}"; \
  echo "Host : ${HOST_VERSION}"; \
  echo "Guest : ${GUEST_VERSION}"; \
  echo "Processor : ${PROCESSOR_NAME}"; \
  echo "Apache : ${APACHE_VERSION}"; \
  echo "PHP : ${PHP_VERSION}"; \
  echo "Java : ${JAVA_VERSION}"; \
} >VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
&& sleep 1s \
&& curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &

# apt upgrade info cached
sleep 3m && apt_result2cache &

# apt upgrade info cached
while true; do \
  for i in {1..144}; do \
    for j in {1..10}; do sleep 60s && echo "${i} ${j}"; done \
     && ss -anpt \
     && ps aux \
     && curl -sS -A "health check" -u "${BASIC_USER}":"${BASIC_PASSWORD}" https://"${RENDER_EXTERNAL_HOSTNAME}"/?$(date +%s); \
  done \
   && apt_result2cache; \
done &

htpasswd -c -b /var/www/html/.htpasswd ${BASIC_USER} ${BASIC_PASSWORD}
chmod 644 /var/www/html/.htpasswd
. /etc/apache2/envvars
exec /usr/sbin/apache2 -DFOREGROUND
