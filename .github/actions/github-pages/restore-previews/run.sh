#!/usr/bin/env bash
set -euo pipefail

source_dir="${INPUT_SOURCE}"
target_dir="${INPUT_TARGET}"

if [ -d "${source_dir}/previews" ]; then
    mkdir -p "${target_dir}/previews"
    cp -R "${source_dir}/previews/." "${target_dir}/previews/"
fi
