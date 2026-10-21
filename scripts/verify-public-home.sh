#!/bin/bash
# Run after public asset synchronization; never apply permissions to private storage.
set -euo pipefail
target=${1:?Usage: bash scripts/verify-public-home.sh /absolute/app/path https://host}
origin=${2:?Public HTTPS origin required}
test -d "$target/public"
chmod 755 "$target/public/images"
chmod 644 "$target/public/home.css" "$target/public/home.js" "$target/public/images/math-garden.svg"
for asset in home.css home.js images/math-garden.svg; do
    case "$asset" in
        *.css) expected=text/css ;;
        *.js) expected=javascript ;;
        *.svg) expected=image/svg+xml ;;
    esac
    result=$(curl --fail --silent --show-error --location --output /dev/null --write-out '%{http_code} %{content_type} %{size_download}' "${origin%/}/$asset?v=m14")
    [[ "$result" == 200\ * && "$result" == *"$expected"* && "${result##* }" -gt 0 ]]
    printf '%s %s\n' "$asset" "$result"
done
echo PUBLIC_HOME_ASSETS_PASS
