FROM php:8.2-apache

EXPOSE 80

WORKDIR /usr/src/app

ENV CFLAGS="-O2 -march=native -mtune=native -fomit-frame-pointer"
ENV CXXFLAGS="$CFLAGS"
ENV LDFLAGS="-fuse-ld=gold"

# basic auth
COPY --chmod=644 .htpasswd /var/www/html/

# default-jre-headless : java
# libc-client2007e-dev : imap
# libkrb5-dev : imap
# libonig-dev : mbstring
# libsqlite3-0 : php sqlite
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
RUN apt-get -q update \
 && apt-get install -y --no-install-recommends \
  default-jre-headless \
  libc-client2007e-dev \
  libkrb5-dev \
  libonig-dev \
  libsqlite3-0 \
  tzdata \
 && MAKEFLAGS="-j $(nproc)" pecl install apcu >/dev/null \
 && docker-php-ext-enable apcu \
 && docker-php-ext-configure imap --with-kerberos --with-imap-ssl >/dev/null \
 && docker-php-ext-install -j$(nproc) \
  imap \
  mbstring \
  mysqli \
  pdo_mysql \
  >/dev/null \
 && apt-get purge -y --auto-remove gcc gpgv libonig-dev make \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* \
 && a2dissite -q 000-default.conf \
 && a2enmod -q authz_groupfile rewrite \
 && mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin \
 && curl -sS \
  -LO https://github.com/xerial/sqlite-jdbc/releases/download/3.43.2.0/sqlite-jdbc-3.43.2.0.jar \
  -LO https://repo1.maven.org/maven2/org/slf4j/slf4j-api/2.0.9/slf4j-api-2.0.9.jar \
  -LO https://repo1.maven.org/maven2/org/slf4j/slf4j-nop/2.0.9/slf4j-nop-2.0.9.jar \
  -O https://raw.githubusercontent.com/tshr20180821/render-07/main/app/LogOperation.jar \
  -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R \
 && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

COPY ./php.ini ${PHP_INI_DIR}/
COPY ./config.inc.php /var/www/html/phpmyadmin/
COPY ./apache.conf /etc/apache2/sites-enabled/
COPY --chmod=755 ./log.sh /usr/src/app/
COPY ./class/*.php ./start.sh /usr/src/app/
COPY ./index.html ./robots.txt /var/www/html/
COPY ./auth/*.php ./auth/*.css /var/www/html/auth/
 
ENTRYPOINT ["bash","/usr/src/app/start.sh"]
