FROM composer/composer:latest

RUN composer global require deployer/deployer:"^6.0"
ADD ./runner.sh /runner.sh
RUN chmod +x /runner.sh