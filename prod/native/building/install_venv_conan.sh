#!/bin/bash
_PYTHON=python3
_PIP=pip3

SCRIPT_DIR=$(dirname "$(realpath "${BASH_SOURCE[0]}")")

#todo path to python3 as arg?

show_help() {
    echo "Usage: $0 --build_path <path to build folder>"
    echo
    echo "Arguments:"
    echo "  --build_path             Required. Build folder path."
    echo "  --force_install          Optional. Forces conan install in existing venv."
    echo
    echo "Example:"
    echo "  $0 --build_path _build/linux-x86-64"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --build_path)
                BUILD_PATH="$2"
                shift
                ;;
            --force_install)
                FORCE_INSTALL=true
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                echo "Unknown parameter passed: $1"
                show_help
                exit 1
                ;;
        esac
        shift
    done
}

parse_args "$@"

if [[ -z "$BUILD_PATH" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

_VENV_PATH=${BUILD_PATH}/venv
_VENV_CREATED=false

if [[ ! -d "${_VENV_PATH}" ]]; then
    echo "Installing python virtual environment in ${_VENV_PATH}"

    ${_PYTHON} -m venv ${_VENV_PATH}

    if [[ ! -d "${BUILD_PATH}" ]]; then
        echo "Virtual environment doesn't exists"
        exit 1
    fi
    _VENV_CREATED=true
else
    echo "Enabling python virtual environment in ${_VENV_PATH}"
fi

source "${_VENV_PATH}/bin/activate"

_PYTHON="${VIRTUAL_ENV}/bin/python3"
_PIP="${VIRTUAL_ENV}/bin/pip3"

cat <<EOF >${BUILD_PATH}/test_venv.py
import logging
import os
import sys

if sys.prefix == sys.base_prefix:
    logging.error("venv not detected")
    exit(1)
else:
    logging.info("Virtual environment detected: " + sys.prefix)
exit(0)
EOF

${_PYTHON} ${BUILD_PATH}/test_venv.py
if [ $? -ne 0 ]; then
    exit 1
fi


if [[ "$_VENV_CREATED" == "true" || "${FORCE_INSTALL}" = true ]]; then
    ${_PIP} --require-virtualenv install -U pip
    # ${_PIP} --require-virtualenv install -U "pyyaml==3.11"
    ${_PIP} --require-virtualenv install -U conan==2.8.0
fi

echo "${_VENV_PATH}/bin/activate"