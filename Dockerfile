FROM composer:latest

ADD composer.json /composer.json
WORKDIR /
RUN apk add --update openssh which
RUN composer install
ADD ./runner.sh /runner.sh
ADD ./deploy.php /deploy.php
ADD ssh_config /etc/ssh/ssh_config
RUN chmod +x /runner.sh