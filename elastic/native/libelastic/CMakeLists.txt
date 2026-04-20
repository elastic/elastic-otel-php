cmake_minimum_required(VERSION 3.26.0)

#TODO use read properties with upstream cmake helpers

# Read version from elastic-otel-php.properties
file(STRINGS "${CMAKE_CURRENT_SOURCE_DIR}/../../../elastic-otel-php.properties" _props)
foreach(_line ${_props})
    if(_line MATCHES "^version=(.+)$")
        set(ELASTIC_OTEL_VERSION "${CMAKE_MATCH_1}")
    endif()
endforeach()

if(NOT ELASTIC_OTEL_VERSION)
    message(FATAL_ERROR "Could not read version from elastic-otel-php.properties")
endif()

message(STATUS "Elastic OTEL version: ${ELASTIC_OTEL_VERSION}")

# Generate version header — per-version targets are created in
# elastic_vendor_inject.cmake (deferred), because _supported_php_versions
# and php-headers packages aren't available yet at add_subdirectory time.
configure_file(
    "${CMAKE_CURRENT_SOURCE_DIR}/elastic_version.h.in"
    "${CMAKE_CURRENT_BINARY_DIR}/elastic_version.h"
    @ONLY
)
