#!/bin/bash
apt-get update
apt-get install -y php php-pgsql php-curl
php -S 0.0.0.0:$PORT bot.php
