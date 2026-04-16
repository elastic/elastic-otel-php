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

# Generate version header
configure_file(
    "${CMAKE_CURRENT_SOURCE_DIR}/elastic_version.h.in"
    "${CMAKE_CURRENT_BINARY_DIR}/elastic_version.h"
    @ONLY
)

set(ELASTIC_SOURCES
    ElasticVendor.cpp
    ElasticConfigProvider.cpp
)

add_library(elastic STATIC ${ELASTIC_SOURCES})

# Explicit build options — this subdirectory is added before upstream's
# global options (otel_set_default_build_options) are applied, so we
# must set them ourselves on this target.
set_target_properties(elastic PROPERTIES
    CXX_STANDARD 23
    CXX_STANDARD_REQUIRED ON
    CXX_EXTENSIONS OFF
    POSITION_INDEPENDENT_CODE ON
    CXX_VISIBILITY_PRESET hidden
)

target_include_directories(elastic
    PUBLIC
        "${CMAKE_CURRENT_SOURCE_DIR}"
        "${CMAKE_CURRENT_BINARY_DIR}"  # for generated elastic_version.h
    PRIVATE
        "${CMAKE_CURRENT_SOURCE_DIR}/../../../upstream/prod/native/libcommon/code"
)

# The elastic static library will be linked into the extension targets.
# The weak symbol getVendorCustomizations() in ElasticVendor.cpp will resolve
# the weak declaration in VendorCustomizationsInterface.h.
