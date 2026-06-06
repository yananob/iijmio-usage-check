#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

APP_ENV=local php tools/migrate_config_to_firestore.php

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
