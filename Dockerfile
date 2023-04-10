FROM php:8.2-apache

WORKDIR /usr/src/app

COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf
RUN a2enmod -q authz_groupfile

COPY ./apache.conf /etc/apache2/sites-enabled/

RUN mkdir -p /var/www/html/auth

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod 644 /var/www/html/.htpasswd

COPY ./class/log.php /usr/src/app/
COPY ./index.html /var/www/html/
COPY ./auth/*.php /var/www/html/auth/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

ENTRYPOINT ["bash","/usr/src/app/start.sh"]
