#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c ./phpstan.neon

./vendor/bin/phpunit --colors=auto --display-notices --display-warnings tests/
# ./vendor/bin/phpunit tests/MyHelloTest.php

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
