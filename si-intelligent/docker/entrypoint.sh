#!/bin/sh
set -e
cd /var/www/html

[ -n "$APP_KEY" ] || { echo "APP_KEY manquante : voir .env.example"; exit 1; }

echo ">>> Attente de MySQL…"
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=3306;dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
  sleep 2
done

echo ">>> Migrations de la base"
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\RolePermissionSeeder' --force

chown -R www-data:www-data storage bootstrap/cache
exec "$@"
