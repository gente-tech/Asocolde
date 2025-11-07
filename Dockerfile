FROM alpine:3.16

# Install packages and remove default server definition
RUN apk --no-cache add php8 \
    php8-ctype \
    php8-curl \
    php8-dom \
    php8-exif \
    php8-fileinfo \
    php8-fpm \
    php8-gd \
    php8-iconv \
    php8-intl \
    php8-mbstring \
    php8-mysqli \
    php8-opcache \
    php8-openssl \
    php8-pecl-imagick \
    php8-pecl-redis \
    php8-pecl-apcu \
    php8-phar \
    php8-session \
    php8-simplexml \
    php8-soap \
    php8-xml \
    php8-xmlreader \
    php8-zip \
    php8-zlib \
    php8-pdo \
    php8-xmlwriter \
    php8-tokenizer \
    php8-pdo_mysql \
    nginx nginx-mod-http-headers-more supervisor curl tzdata htop mysql-client dcron \  
    gnupg unixodbc-dev git imagemagick-libs

# Symlink php7 => php
# RUN ln -s /usr/bin/php8 /usr/bin/php

# Install PHP tools
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Install Drush
# composer require --dev drush/drush
RUN composer --no-interaction --no-progress --ansi global require drush/drush && \
    composer --no-interaction --no-progress --ansi global update

# Configure nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Configure PHP-FPM
COPY docker/fpm-pool.conf /etc/php8/php-fpm.d/www.conf
COPY docker/php.ini /etc/php8/conf.d/custom.ini

# Configure supervisord
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Setup document root
RUN mkdir -p /opt/drupal

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody.nobody /opt/drupal && \
  chown -R nobody.nobody /run && \
  chown -R nobody.nobody /var/www && \
  chown -R nobody.nobody /var/lib/nginx && \
  chown -R nobody.nobody /var/log/nginx && \
  chown -R nobody.nobody /var/log/php8

# Switch to use a non-root user from here on
USER nobody

ENV DRUPAL_VERSION 9.5.2

# Add application
WORKDIR /opt/drupal
#COPY --chown=nobody src/ /opt/drupal

RUN set -eux; \
	export COMPOSER_HOME="$(mktemp -d)"; \
	composer create-project --no-interaction --no-progress --ansi "drupal/recommended-project:$DRUPAL_VERSION" ./; \
	chown -R nobody.nobody web/sites web/modules web/themes; \
	ln -sf /opt/drupal/web /var/www/drupal; \
	rm -rf "$COMPOSER_HOME"

ENV PATH="/opt/drupal/web/vendor/bin:/opt/drupal/vendor/bin:${PATH}":

# settings.php
COPY --chown=nobody:nobody docker/settings.php /opt/drupal/web/sites/default/settings.php
COPY --chown=nobody:nobody composer.json /opt/drupal

RUN composer update
RUN composer --no-interaction --no-progress --ansi Install

RUN chmod +x vendor/bin/drush

WORKDIR /opt/drupal/web

RUN mkdir -p /opt/drupal/web/modules/custom/ &&  \
    mkdir -p /opt/drupal/web/themes/custom/ &&  \
    mkdir -p /opt/drupal/web/sites/default/files

# COPY --chown=nobody:nobody web/modules/custom modules/custom/
COPY --chown=nobody:nobody web/themes/custom themes/custom/
COPY --chown=nobody:nobody config/sync/*.yml /opt/drupal/config/sync/

# COPY --chown=nobody:nobody src/root .

RUN chown -R nobody:nobody /opt/drupal/web

#RUN chmod -R 777 /opt/drupal/config

RUN find . -type d -exec chmod u=rwx,g=rx,o= '{}' \;
RUN find . -type f -exec chmod u=rw,g=r,o= '{}' \;

# Let supervisord start nginx & php-fpm
# drush -y cr && drush -y cim
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]