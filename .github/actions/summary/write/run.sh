#!/usr/bin/env bash
set -euo pipefail

if [ -z "${INPUT_MARKDOWN}" ]; then
    exit 0
fi

printf '%s\n' "${INPUT_MARKDOWN}" >> "${GITHUB_STEP_SUMMARY}"
