#!/bin/bash
set -eu

# if [ "$#" -ne 1 ]; then
#   echo ""
#   echo "  Invalid arguments."
#   echo "  Usage: $0 \"SECRET_KEY1,SECRET_KEY2\""
#   echo ""
#   exit 1
# fi

echo "Unsetting secrets..."

# Remove credentials
# IFS=',' read -ra SECRETS <<< "$1"
for secret in "${SECRETS[@]}"; do
    unset "$secret"
done
