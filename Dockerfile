FROM composer:latest

RUN composer global require deployer/deployer:"^6.0"
ADD ./runner.sh /runner.sh
ADD ./deploy.php /deploy.php
RUN chmod +x /runner.sh