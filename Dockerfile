FROM php:8.2-apache

WORKDIR /usr/src/app

ENTRYPOINT ["bash","/usr/src/app/start.sh"]
