#!/usr/bin/env bash
set -euo pipefail
APP="/home4/mcied45x/repositories/MCI-Test-Series"
LIVE="/home4/mcied45x/test.mciedu.com"
PHP83="/opt/cpanel/ea-php83/root/usr/bin/php"
COMPOSER="/opt/cpanel/composer/bin/composer"

cd "$APP"
test -f .env
"$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$PHP83" artisan optimize:clear
"$PHP83" artisan migrate --force
"$PHP83" artisan db:seed --force
chmod -R ug+rwX storage bootstrap/cache
cp -a public/. "$LIVE/"
sed -i "s#__DIR__.'/../storage#__DIR__.'/../repositories/MCI-Test-Series/storage#; s#__DIR__.'/../vendor#__DIR__.'/../repositories/MCI-Test-Series/vendor#; s#__DIR__.'/../bootstrap#__DIR__.'/../repositories/MCI-Test-Series/bootstrap#" "$LIVE/index.php"
test -e "$LIVE/storage" || ln -s "$APP/storage/app/public" "$LIVE/storage"
"$PHP83" artisan optimize
curl -fsS https://test.mciedu.com/ >/dev/null
echo "MCI_TEST_SERIES_DEPLOY_OK"
