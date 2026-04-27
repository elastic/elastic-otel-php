# ===========================================================================
# elastic_vendor_inject.cmake
#
# Injected into the upstream opentelemetry-php-distro CMake build via:
#   -DCMAKE_PROJECT_INCLUDE=<path>/elastic_vendor_inject.cmake
#
# This file is evaluated once, right after the upstream project() call.
# It adds the Elastic vendor static library and links it into each
# extension target (resolving the weak getVendorCustomizations symbol).
# ===========================================================================

# Guard against double-inclusion (CMAKE_PROJECT_INCLUDE fires for every project())
if(DEFINED _ELASTIC_VENDOR_INJECTED)
    return()
endif()
set(_ELASTIC_VENDOR_INJECTED TRUE)

# Path to the elastic vendor library source (relative to repo root)
# CMAKE_SOURCE_DIR here is upstream/prod/native (where cmake runs from)
# The repo root is 3 levels up: upstream/prod/native -> upstream/prod -> upstream -> root
get_filename_component(_REPO_ROOT "${CMAKE_SOURCE_DIR}/../../.." ABSOLUTE)
set(ELASTIC_VENDOR_DIR "${_REPO_ROOT}/elastic_prod/native/libelastic" CACHE PATH "Elastic vendor lib source" FORCE)

message(STATUS "EDOT: Will inject Elastic vendor library from ${ELASTIC_VENDOR_DIR}")

if(NOT EXISTS "${ELASTIC_VENDOR_DIR}/CMakeLists.txt")
    message(FATAL_ERROR "EDOT: Elastic vendor library not found at ${ELASTIC_VENDOR_DIR}")
endif()

# Run add_subdirectory now (non-deferred) to generate version header.
# The CMakeLists.txt only does configure_file — per-version targets are
# created below in the deferred function.
add_subdirectory("${ELASTIC_VENDOR_DIR}" "${CMAKE_BINARY_DIR}/elastic_vendor")

# Defer target creation and linking to run at the end of the top-level
# CMakeLists.txt, when _supported_php_versions and php-headers are available.
cmake_language(DEFER DIRECTORY "${CMAKE_SOURCE_DIR}" CALL _elastic_create_and_link)

function(_elastic_create_and_link)
    if(NOT DEFINED _supported_php_versions)
        message(FATAL_ERROR "EDOT: _supported_php_versions not defined")
    endif()

    set(_elastic_sources
        "${ELASTIC_VENDOR_DIR}/ElasticVendor.cpp"
        "${ELASTIC_VENDOR_DIR}/ElasticConfigProvider.cpp"
    )

    foreach(_php_version ${_supported_php_versions})
        set(_elastic elastic_${_php_version})
        set(_target opentelemetry_php_distro_${_php_version})

        add_library(${_elastic} STATIC ${_elastic_sources})

        set_target_properties(${_elastic} PROPERTIES
            CXX_STANDARD 23
            CXX_STANDARD_REQUIRED ON
            CXX_EXTENSIONS OFF
            POSITION_INDEPENDENT_CODE ON
            CXX_VISIBILITY_PRESET hidden
        )

        target_compile_definitions(${_elastic}
            PRIVATE "PHP_ATOM_INC" "PHP_ABI=${CMAKE_C_COMPILER_ABI}"
        )

        target_include_directories(${_elastic}
            PUBLIC
                "${ELASTIC_VENDOR_DIR}"
                "${CMAKE_BINARY_DIR}/elastic_vendor"  # for generated elastic_version.h
            PRIVATE
                "${CMAKE_SOURCE_DIR}/libcommon/code"
                "${CMAKE_SOURCE_DIR}/libphpbridge/code"
                "${php-headers-${_php_version}_INCLUDE_DIRS}"
                "${php-headers-${_php_version}_INCLUDE_DIRS}/ext"
                "${php-headers-${_php_version}_INCLUDE_DIRS}/main"
                "${php-headers-${_php_version}_INCLUDE_DIRS}/TSRM"
                "${php-headers-${_php_version}_INCLUDE_DIRS}/Zend"
        )

        if(TARGET ${_target})
            message(STATUS "EDOT: Linking ${_elastic} into ${_target}")
            target_link_libraries(${_target}
                PRIVATE $<LINK_LIBRARY:WHOLE_ARCHIVE,${_elastic}>
            )
        else()
            message(WARNING "EDOT: Target ${_target} not found, skipping")
        endif()
    endforeach()
endfunction()
