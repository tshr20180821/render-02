FROM php:8.2-apache

WORKDIR /usr/src/app

# libc-client2007e-dev : imap
# libkrb5-dev : imap
# libonig-dev : mbstring
# libsqlite3-0 : php sqlite
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
RUN apt-get update \
 && apt-get install -y \
  default-jdk \
  libc-client2007e-dev \
  libkrb5-dev \
  libonig-dev \
  libsqlite3-0 \
  tzdata \
 && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
 && docker-php-ext-install -j$(nproc) \
  imap \
  mbstring \
  mysqli \
  pdo_mysql \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*
 
COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf \
 && a2enmod -q authz_groupfile rewrite

COPY ./apache.conf /etc/apache2/sites-enabled/
COPY --chmod=755 ./log.sh /usr/src/app/

RUN mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod +x /usr/src/app/log.sh \
 && chmod 644 /var/www/html/.htpasswd

COPY ./class/*.php /usr/src/app/
COPY ./index.html /var/www/html/
COPY ./robots.txt /var/www/html/
COPY ./auth/*.php /var/www/html/auth/
COPY ./auth/*.css /var/www/html/auth/

COPY ./start.sh /usr/src/app/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

RUN curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R

COPY ./config.inc.php /var/www/html/phpmyadmin/

COPY ./src/*.java /usr/src/app/
RUN curl -L -O https://github.com/xerial/sqlite-jdbc/releases/download/3.43.2.0/sqlite-jdbc-3.43.2.0.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-api/2.0.9/slf4j-api-2.0.9.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-nop/2.0.9/slf4j-nop-2.0.9.jar \
 && javac /usr/src/app/*.java
 
ENTRYPOINT ["bash","/usr/src/app/start.sh"]
