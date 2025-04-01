cmake_minimum_required(VERSION 3.26.0)

include(FetchContent)

# set path for our local includes
list(APPEND CMAKE_MODULE_PATH "${CMAKE_SOURCE_DIR}/building/cmake")
list(APPEND CMAKE_PREFIX_PATH "${CMAKE_BINARY_DIR}/build/${CMAKE_BUILD_TYPE}/generators")

foreach(path ${CMAKE_PREFIX_PATH})
    message(STATUS "Prefix Path: ${path}")
endforeach()

foreach(path ${CMAKE_MODULE_PATH})
    message(STATUS "Module Path: ${path}")
endforeach()


include(elastic_read_properties)
elastic_read_properties("../../elastic-otel-php.properties" "_PROJECT_PROPERTIES_")

set(_PROJECT_PROPERTIES_VERSION_SHORT ${_PROJECT_PROPERTIES_VERSION})

if(DEFINED ENV{GITHUB_SHA})
    set(GITHUB_SHA $ENV{GITHUB_SHA})
    message(STATUS "GITHUB_SHA: ${GITHUB_SHA}")
else()
    message(STATUS "GITHUB_SHA is not defined in the environment. Trying to get hash from source tree")
    include(elastic_get_git_version)
    elastic_git_get_hash(_COMMIT_HASH_REV)
    set(_PROJECT_PROPERTIES_VERSION "${_PROJECT_PROPERTIES_VERSION}~${_COMMIT_HASH_REV}")
endif()

message(STATUS "Project version: ${_PROJECT_PROPERTIES_VERSION}")

project("elastic-otel-php"
    VERSION ${_PROJECT_PROPERTIES_VERSION_SHORT}
    LANGUAGES C CXX
)

set(RELEASE_BUILD false)
set(DEBUG_BUILD false)
if(CMAKE_BUILD_TYPE STREQUAL "Release")
    set(RELEASE_BUILD true)
elseif(CMAKE_BUILD_TYPE STREQUAL "Debug")
    set(DEBUG_BUILD true)
else()
    message(FATAL_ERROR "Build type not supported: ${CMAKE_BUILD_TYPE}")
endif()


if (SKIP_CONAN_INSTALL)
    message(STATUS "Skipping conan dependencies installation")
else()
    message(STATUS "${CMAKE_SOURCE_DIR}/building/install_dependencies.sh  --build_output_path ${CMAKE_BINARY_DIR} --build_preset ${CMAKE_PRESET_NAME} --build_type ${CMAKE_BUILD_TYPE}")
    execute_process(
        COMMAND ${CMAKE_SOURCE_DIR}/building/install_dependencies.sh  --build_output_path ${CMAKE_BINARY_DIR} --build_preset ${CMAKE_PRESET_NAME} --build_type ${CMAKE_BUILD_TYPE}
        COMMAND_ERROR_IS_FATAL ANY
    )
endif()

# Detect Alpine/MUSLC build
if(EXISTS /etc/alpine-release)
    set(MUSL_BUILD true)
endif()

if ((NOT ${CMAKE_SYSTEM_NAME} STREQUAL "Linux") OR (NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "x86_64" AND NOT ${CMAKE_SYSTEM_PROCESSOR} STREQUAL "aarch64"))
     message(FATAL_ERROR "System or architecture not supported")
endif()

include(elastic_set_default_build_options)

message(STATUS "Detected ${CMAKE_CXX_COMPILER_ID} compiler version: ${CMAKE_CXX_COMPILER_VERSION}")

include(elastic_conan_debugsymbols)

elastic_array_to_list(${_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS} _supported_php_versions)

message(STATUS "Supported PHP versions ${_supported_php_versions}")

FetchContent_Declare(
  OpenTelemetryProto
  GIT_REPOSITORY https://github.com/open-telemetry/opentelemetry-proto.git
  GIT_TAG        ${_PROJECT_PROPERTIES_OTEL_PROTO_VERSION}
)
FetchContent_MakeAvailable(OpenTelemetryProto)

set(_OPENTELEMETRY_PROTO_PATH "${CMAKE_BINARY_DIR}/_deps/opentelemetryproto-src")

message(STATUS "OpenTelemetry protobuf available at ${_OPENTELEMETRY_PROTO_PATH}")


find_package(CURL 8.10.1 REQUIRED)
find_package(libunwind 1.8.1 REQUIRED)
find_package(Boost 1.86 REQUIRED CONFIG)
find_package(GTest 1.15.0 REQUIRED)
find_package(magic_enum 0.9.6 REQUIRED)
find_package(protobuf REQUIRED)

foreach(_php_version ${_supported_php_versions})
    find_package(php-headers-${_php_version} ${_PROJECT_PROPERTIES_PHP_HEADERS_VERSION} REQUIRED)
endforeach()

enable_testing()

add_subdirectory(libcommon)
add_subdirectory(libphpbridge)
add_subdirectory(libotlp)
add_subdirectory(loader)
add_subdirectory(extension)
add_subdirectory(phpbridge_extension)
