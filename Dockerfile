FROM php:8.3-apache

EXPOSE 80

SHELL ["/bin/bash", "-c"]

WORKDIR /usr/src/app

ENV CFLAGS="-O2 -march=native -mtune=native -fomit-frame-pointer"
ENV CXXFLAGS="${CFLAGS}"
ENV LDFLAGS="-fuse-ld=gold"

COPY ./php.ini ${PHP_INI_DIR}/
COPY ./apache.conf /etc/apache2/sites-enabled/

ENV APACHE_VERSION="2.4.58-1"
ENV PHPMYADMIN_VERSION="5.2.1"
ENV SQLITE_JDBC_VERSION="3.50.2.0"

# default-jre-headless : java
# iproute2 : ss
# libc-client2007e-dev : imap
# libkrb5-dev : imap
# libonig-dev : mbstring
# libsqlite3-0 : php sqlite
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
RUN set -x \
 && date -d '+9 hours' +'%Y-%m-%d %H:%M:%S' >./BuildDateTime.txt \
 && savedAptMark="$(apt-mark showmanual)" \
 && { \
  echo "https://github.com/xerial/sqlite-jdbc/releases/download/$SQLITE_JDBC_VERSION/sqlite-jdbc-$SQLITE_JDBC_VERSION.jar"; \
  echo "https://raw.githubusercontent.com/tshr20180821/render-07/main/app/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.tar.xz"; \
  echo "https://raw.githubusercontent.com/tshr20180821/render-07/main/app/slf4j-api-2.0.9.jar"; \
  echo "https://raw.githubusercontent.com/tshr20180821/render-07/main/app/slf4j-nop-2.0.9.jar"; \
  echo "https://raw.githubusercontent.com/tshr20180821/render-07/main/app/LogOperation.jar"; \
  echo "https://raw.githubusercontent.com/tshr20180821/render-07/main/app/gpg"; \
  } >download.txt \
 && xargs -P2 -n1 curl -sSLO <download.txt \
 && apt-get -qq update
RUN set -x \
 && apt-get install -y --no-install-recommends \
  default-jre-headless \
  iproute2 \
  libkrb5-dev \
  libonig-dev \
  libpq-dev \
  libsqlite3-0 \
  tzdata
# RUN set -x \
#  && apt-get install -y --no-install-recommends \
#   uw-imap-dev
# RUN set -x \
#  && pushd /usr/local/src \
#  && curl -O https://pecl.php.net/get/imap-1.0.3.tgz \
#  && tar xvzf imap-1.0.3.tgz \
#  && pushd imap-1.0.3 \
#  && /usr/local/bin/phpize \
#  && 
RUN set -x \
 && nproc=$(nproc) \
 && MAKEFLAGS="-j ${nproc}" pecl install apcu >/dev/null \
 && MAKEFLAGS="-j ${nproc}" pecl install redis >/dev/null \
 && docker-php-ext-enable \
  apcu \
  redis \
 && docker-php-ext-configure imap --with-kerberos --with-imap-ssl >/dev/null \
 && docker-php-ext-install -j${nproc} \
  imap \
  mbstring \
  mysqli \
  opcache \
  pdo_mysql \
  pdo_pgsql \
  pgsql \
  >/dev/null
RUN set -x \
 && apt-get upgrade -y --no-install-recommends \
 && pecl clear-cache \
 && apt-get purge -y --auto-remove \
  gcc \
  gpgv \
  libonig-dev \
  make \
  re2c \
 && apt-mark auto '.*' >/dev/null \
 && apt-mark manual ${savedAptMark} >/dev/null \
 && find /usr/local -type f -executable -exec ldd '{}' ';' | \
  awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); print so }' | \
  sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual >/dev/null 2>&1 \
 && apt-mark manual \
  default-jre-headless \
  iproute2 \
 && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin \
 && a2dissite -q 000-default.conf \
 && a2enmod -q \
  authz_groupfile \
  rewrite \
 && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
 && tar xf ./phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm ./phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.tar.xz ./download.txt ./gpg \
 && chown www-data:www-data /var/www/html/phpmyadmin -R \
 && echo '<HTML />' >/var/www/html/index.html \
 && { \
  echo 'User-agent: *'; \
  echo 'Disallow: /'; \
  } >/var/www/html/robots.txt

COPY ./config.inc.php /var/www/html/phpmyadmin/
COPY ./class/*.php ./start.sh ./
COPY --chmod=755 ./log.sh ./
COPY ./auth/*.php ./auth/*.css /var/www/html/auth/

STOPSIGNAL SIGWINCH

ENTRYPOINT ["/bin/bash","/usr/src/app/start.sh"]
