#!/usr/bin/env bash
# Installs, lints and tests the PHP SDK with the 100% coverage gate. CI runs exactly this.
set -euo pipefail

cd "$(dirname "$0")/.."

php=(php)

# Coverage needs pcov or Xdebug. Herd ships Xdebug without loading it, so load it here when present.
if ! php -m | grep -qiE '^(pcov|xdebug)$'; then
    herd_xdebug="/Applications/Herd.app/Contents/Resources/xdebug/xdebug-$(php -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;')-$(uname -m).so"

    if [ -f "$herd_xdebug" ]; then
        php=(php -d "zend_extension=$herd_xdebug")
    else
        echo "Coverage needs the pcov or xdebug extension." >&2
        exit 1
    fi
fi

composer install --no-interaction --no-progress --prefer-dist
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
XDEBUG_MODE=coverage "${php[@]}" vendor/bin/pest --coverage --min=100
