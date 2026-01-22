FROM php:8.3-apache

EXPOSE 80

ENV CFLAGS="-O2 -march=native -mtune=native -fomit-frame-pointer"
ENV CXXFLAGS="$CFLAGS"
ENV LDFLAGS="-fuse-ld=gold"
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /usr/src/app

COPY ./composer.json /usr/src/app

# ENV SQLITE_JDBC_VERSION="3.50.3.0"
ENV SQLITE_JDBC_VERSION="3.51.1.0"
ENV SLF4J_VERSION="2.0.17"

RUN set -x \
 && date -d '+9 hours' +'%Y-%m-%d %H:%M:%S' >./BuildDateTime.txt \
 && apt-get -qq update \
 && DEBIAN_FRONTEND=noninteractive apt-get -q install -y --no-install-recommends \
  build-essential \
  curl \
  default-jre-headless \
  gcc-x86-64-linux-gnu \
  iproute2 \
  libfreetype-dev \
  libgssapi-krb5-2 \
  libjpeg-dev \
  libkrb5-dev \
  libmemcached-dev \
  libonig-dev \
  libpng-dev \
  libsasl2-modules \
  libssl-dev \
  memcached \
  sasl2-bin \
  tzdata \
  unzip \
  >/dev/null

# libc-client2007e-dev

RUN set -x \
 && nproc=$(nproc) \
 && MAKEFLAGS="-j ${nproc}" pecl install apcu >/dev/null

RUN set -x \
 && nproc=$(nproc) \
 && MAKEFLAGS="-j ${nproc}" pecl install memcached --enable-memcached-sasl >/dev/null

RUN set -x \
 && nproc=$(nproc) \
 && MAKEFLAGS="-j ${nproc}" pecl install redis >/dev/null

RUN set -x \
 && nproc=$(nproc) \
 && docker-php-ext-enable \
  apcu \
  memcached \
  redis \
 && docker-php-ext-configure imap --with-kerberos --with-imap-ssl >/dev/null \
 && docker-php-ext-install -j${nproc} \
  mbstring \
  imap \
  sockets \
  >/dev/null
  
RUN set -x \
 && nproc=$(nproc) \
 && composer install --apcu-autoloader \
 && composer suggest \
 && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y --no-install-recommends \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin \
 && curl -sS \
  -LO https://github.com/xerial/sqlite-jdbc/releases/download/$SQLITE_JDBC_VERSION/sqlite-jdbc-$SQLITE_JDBC_VERSION.jar \
  -LO https://repo1.maven.org/maven2/org/slf4j/slf4j-api/$SLF4J_VERSION/slf4j-api-$SLF4J_VERSION.jar \
  -LO https://repo1.maven.org/maven2/org/slf4j/slf4j-nop/$SLF4J_VERSION/slf4j-nop-$SLF4J_VERSION.jar \
  -O https://raw.githubusercontent.com/tshr20180821/render-07/main/app/LogOperation.jar \
 && a2dissite -q 000-default.conf \
 && a2enmod -q \
  authz_groupfile \
  brotli \
  proxy \
  proxy_http \
  rewrite \
 && mkdir -p /var/php/class \
 && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

# RUN ls -lang /var/www/vendor/

# COPY ./config/php.ini ${PHP_INI_DIR}/

# COPY ./www/index.html ./www/robots.txt ./www/*.ico /var/www/html/

# COPY --chmod=755 ./*.sh /usr/src/app/
# COPY ./config/apache.conf /etc/apache2/sites-enabled/

# COPY ./class/*.php /var/php/class/
# COPY ./www/auth/*.css ./www/auth/*.php /var/www/html/auth/
# COPY ./www/*.php /var/www/html/

COPY ./start.sh /usr/src/app/

# RUN curl -o /tmp/phpliteadmin-dev.zip https://www.phpliteadmin.org/phpliteadmin-dev.zip \
#  && unzip -d /var/www/html/phpliteadmin phpliteadmin-dev.zip

ENTRYPOINT ["bash","/usr/src/app/start.sh"]
