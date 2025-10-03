#!/bin/bash

echo "Starting build process..."

# آپدیت سیستم
apt-get update -y

# نصب پکیج‌های مورد نیاز PHP
apt-get install -y \
    php-pgsql \
    php-curl \
    php-json \
    php-mbstring \
    php-xml

# ایجاد دایرکتوری‌های مورد نیاز
mkdir -p /tmp/telegram_bot_data

echo "✅ Build completed - PHP with PostgreSQL support is ready!"