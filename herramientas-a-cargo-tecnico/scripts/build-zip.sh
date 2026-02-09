#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="herramientas-a-cargo-tecnico"

VERSION="$(php -r "if (file_exists('${ROOT_DIR}/${PLUGIN_SLUG}.php')) { \n  \$contents = file_get_contents('${ROOT_DIR}/${PLUGIN_SLUG}.php');\n  if (preg_match('/Version:\\s*([^\\n\\r]+)/', \$contents, \$m)) { echo trim(\$m[1]); }\n}" )"

if [[ -z "${VERSION}" ]]; then
  echo "No se pudo detectar la versión del plugin." >&2
  exit 1
fi

OUTPUT_DIR="${ROOT_DIR}/dist"
OUTPUT_ZIP="${OUTPUT_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

mkdir -p "${OUTPUT_DIR}"

echo "Generando ${OUTPUT_ZIP}..."
(
  cd "${ROOT_DIR}"
  rm -f "${OUTPUT_ZIP}"
  zip -r "${OUTPUT_ZIP}" . \
    -x "dist/*" \
    -x "scripts/*" \
    -x ".git/*" \
    -x ".gitignore" \
    -x "*.zip"
)

echo "ZIP generado en ${OUTPUT_ZIP}"
