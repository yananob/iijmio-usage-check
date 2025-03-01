#!/bin/bash
set -eu

gcloud pubsub topics publish iijmio-usage-check --message="test!"
