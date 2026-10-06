# تشغيل محلي: PHP 8.3 + Apache (نفس بيئة Hostinger تقريباً)
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo_mysql gd \
 && a2enmod rewrite headers expires \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*

RUN { echo 'upload_max_filesize=4M'; echo 'post_max_size=8M'; echo 'date.timezone=Asia/Riyadh'; echo 'max_execution_time=300'; } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
