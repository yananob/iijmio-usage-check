#!/bin/bash
set -eu

# choose deploy type
# bash ./_cf-common/deploy/deploy_php_http.sh . XXXX
bash ./_cf-common/deploy/deploy_php_event.sh . iijmio-usage-check
