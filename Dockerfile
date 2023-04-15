FROM php:8.2-apache

WORKDIR /usr/src/app

RUN apt-get update \
 && apt-get install -y \
  libonig-dev \
  libsqlite3-0 \
 && docker-php-ext-install -j$(nproc) pdo_mysql mysqli mbstring \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*
 
COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf
RUN a2enmod -q authz_groupfile

COPY ./apache.conf /etc/apache2/sites-enabled/

RUN mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod 644 /var/www/html/.htpasswd

COPY ./class/*.php /usr/src/app/
COPY ./index.html /var/www/html/
COPY ./auth/*.php /var/www/html/auth/

COPY ./start.sh /usr/src/app/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

RUN curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R

COPY ./config.inc.php /var/www/html/phpmyadmin/

ENTRYPOINT ["bash","/usr/src/app/start.sh"]
