FROM php:8.2-alpine

# نصب extension های مورد نیاز
RUN apk update && apk add --no-cache \
    postgresql-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql

# کپی فایل‌های پروژه
COPY . /var/www/html
WORKDIR /var/www/html

# اجرای ربات
CMD ["php", "-S", "0.0.0.0:8080", "bot.php"]
