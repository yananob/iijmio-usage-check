#!/bin/bash
set -eu

gcloud pubsub topics publish iijmio-usage --message="test!"
