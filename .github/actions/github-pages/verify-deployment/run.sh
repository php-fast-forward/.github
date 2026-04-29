#!/usr/bin/env bash
set -euo pipefail

base_url="${INPUT_BASE_URL}"
title="${INPUT_TITLE}"
attempts="${INPUT_ATTEMPTS}"
delay="${INPUT_DELAY}"

check_url() {
    url="$1"
    label="$2"

    for attempt in $(seq 1 "${attempts}"); do
        echo "Checking ${label}: ${url} (attempt ${attempt}/${attempts})"

        http_status="$(curl --silent --show-error --output /dev/null --location --write-out '%{http_code}' "${url}" 2>/tmp/curl-error.log || true)"

        if [ "${http_status}" -ge 200 ] && [ "${http_status}" -lt 400 ]; then
            echo "${label} is reachable (${http_status})."

            return 0
        fi

        curl_error="$(cat /tmp/curl-error.log)"

        if [ -n "${curl_error}" ]; then
            echo "Attempt ${attempt} failed for ${label}: ${url} (curl error: ${curl_error})"
        else
            echo "Attempt ${attempt} failed for ${label}: ${url} (HTTP ${http_status})"
        fi

        if [ "${attempt}" -lt "${attempts}" ]; then
            sleep "${delay}"
        fi
    done

    echo "::error title=${title}::${label} is not reachable at ${url}. Last HTTP status: ${http_status}${curl_error:+; curl error: ${curl_error}}"

    return 1
}

while IFS='|' read -r relative_path label; do
    if [ -z "${label}" ]; then
        continue
    fi

    check_url "${base_url}${relative_path}" "${label}"
done <<EOF
${INPUT_CHECKS}
EOF
