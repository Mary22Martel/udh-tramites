#!/usr/bin/env bash
# Instalación en macOS / Linux
set -e
composer install --no-interaction
[ -f .env ] || cp .env.example .env
php artisan key:generate --force
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate:fresh --seed --force
if command -v npm >/dev/null; then npm install && npm run build; fi
echo "Listo. Inicia con:  php -d upload_max_filesize=20M -d post_max_size=25M artisan serve"
