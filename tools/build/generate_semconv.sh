#!/usr/bin/env bash

set -euo pipefail

source ./tools/read_properties.sh

read_properties elastic-otel-php.properties _PROJECT_PROPERTIES

SEMCONV_VERSION=${_PROJECT_PROPERTIES_OTEL_SEMCONV_VERSION}
BUILD_DIR=$(realpath "build/semconv")
OUTPUT_PATH=$(realpath "prod/native/libsemconv/include/opentelemetry/semconv")
TEMPLATES_PATH=$(realpath "prod/native/libsemconv/templates/")

mkdir -p "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/output"

CONTAINER_NAME="temp_git_checkout_$$"

docker run --rm \
    -u $(id -u ${USER}):$(id -g ${USER}) \
    --name "${CONTAINER_NAME}" \
    --entrypoint sh \
    -v "${BUILD_DIR}:/work" \
    alpine/git -c "
  set -e
  cd /work

  rm -rf semantic-conventions || true

  git init semantic-conventions
  cd semantic-conventions
  git remote add origin https://github.com/open-telemetry/semantic-conventions.git
  git config core.sparseCheckout true
  echo 'model' > .git/info/sparse-checkout
  git pull --depth 1 origin v$SEMCONV_VERSION
  cd ..

"

mkdir -p "${OUTPUT_PATH}"

TARGET=./
OUTPUT=./

docker run --rm \
    -u $(id -u ${USER}):$(id -g ${USER}) \
    -v "${BUILD_DIR}/semantic-conventions:/source" \
    -v "${BUILD_DIR}/output:/output" \
    -v "${TEMPLATES_PATH}:/templates" \
    otel/weaver:v0.13.2 \
    registry generate \
    --registry=/source \
    --templates=/templates \
    ${TARGET} \
    /output/${TARGET} \
    --param filter=all \
    --param output=${OUTPUT} \
    --param schema_url=https://opentelemetry.io/schemas/v${SEMCONV_VERSION}

cp "${BUILD_DIR}/output/"*.h "${OUTPUT_PATH}/"
echo "Files copied to: ${OUTPUT_PATH}"
echo "Removing temporary build files"

docker run --rm \
    -u $(id -u ${USER}):$(id -g ${USER}) \
    --entrypoint sh \
    -v "${BUILD_DIR}:/work" \
    alpine/git -c "rm -rf /work/*"

rm -rf "${BUILD_DIR}"
