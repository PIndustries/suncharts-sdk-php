#!/usr/bin/env bash
# Regenerate src/Generated (low-level operations + models) from openapi.json with a pinned openapi-generator.
# The ergonomic client (src/Client.php, src/Auth, src/Exception, src/Response.php) is hand-written and never touched here.
#   scripts/generate.sh                 # uses ./openapi.json
#   SPEC=path/to/openapi.json scripts/generate.sh
set -euo pipefail
cd "$(dirname "$0")/.."
OAG_VERSION=7.26.0
SPEC=${SPEC:-openapi.json}
[ "$SPEC" != openapi.json ] && cp "$SPEC" openapi.json
JAR=${OAG_JAR:-${XDG_CACHE_HOME:-$HOME/.cache}/openapi-generator/openapi-generator-cli-$OAG_VERSION.jar}
if [ ! -f "$JAR" ]; then
  mkdir -p "$(dirname "$JAR")"
  curl -sSfLo "$JAR" "https://repo1.maven.org/maven2/org/openapitools/openapi-generator-cli/$OAG_VERSION/openapi-generator-cli-$OAG_VERSION.jar"
fi
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
python3 scripts/normalize-spec.py openapi.json > "$TMP/spec.json"
java -jar "$JAR" generate -g php -i "$TMP/spec.json" -o "$TMP/out" --skip-validate-spec \
  --additional-properties='invokerPackage=PIndustries\SunCharts\Generated,srcBasePath=src,hideGenerationTimestamp=true,artifactVersion=generated' \
  --global-property=apiDocs=false,modelDocs=false,apiTests=false,modelTests=false > "$TMP/gen.log" 2>&1 || { cat "$TMP/gen.log"; exit 1; }
rm -rf src/Generated && cp -R "$TMP/out/src" src/Generated
echo "generated src/Generated from $(python3 -c 'import json;print(json.load(open("openapi.json"))["info"]["version"])') spec"
