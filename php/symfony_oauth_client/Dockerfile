FROM php:7.0

EXPOSE 8888
RUN yes | pecl install xdebug && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini && echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/xdebug.ini && echo "xdebug.remote_autostart=off" >> /usr/local/etc/php/conf.d/xdebug.ini && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/xdebug.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN apt-get update && apt-get install -y git libpng-dev
RUN docker-php-ext-install zip && docker-php-ext-enable zip

COPY . /app
WORKDIR /app

RUN composer install --prefer-source --no-interaction
CMD bin/console server:run 0.0.0.0:8888