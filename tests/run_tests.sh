#!/bin/bash
set -eu

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c ./phpstan.neon

./vendor/bin/phpunit --colors=auto --display-notices --display-warnings tests/
# ./vendor/bin/phpunit tests/MyHelloTest.php
