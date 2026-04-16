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

# Guard against double-inclusion (CMAKE_PROJECT_INCLUDE_AFTER fires for every project())
if(DEFINED _ELASTIC_VENDOR_INJECTED)
    return()
endif()
set(_ELASTIC_VENDOR_INJECTED TRUE)

# Path to the elastic vendor library source (relative to repo root)
# CMAKE_SOURCE_DIR here is upstream/prod/native (where cmake runs from)
# The repo root is 3 levels up: upstream/prod/native -> upstream/prod -> upstream -> root
get_filename_component(_REPO_ROOT "${CMAKE_SOURCE_DIR}/../../.." ABSOLUTE)
set(ELASTIC_VENDOR_DIR "${_REPO_ROOT}/elastic/native/libelastic" CACHE PATH "Elastic vendor lib source" FORCE)

message(STATUS "EDOT: Will inject Elastic vendor library from ${ELASTIC_VENDOR_DIR}")

if(NOT EXISTS "${ELASTIC_VENDOR_DIR}/CMakeLists.txt")
    message(FATAL_ERROR "EDOT: Elastic vendor library not found at ${ELASTIC_VENDOR_DIR}")
endif()

# Add elastic vendor library immediately (add_subdirectory cannot be deferred).
# Note: this runs right after project(), before upstream sets global build options
# (C++23, PIC, etc.), so elastic/CMakeLists.txt must set its own compile options.
add_subdirectory("${ELASTIC_VENDOR_DIR}" "${CMAKE_BINARY_DIR}/elastic_vendor")

# Defer only the target_link_libraries to run at the end of the top-level
# CMakeLists.txt, when extension targets (opentelemetry_php_distro_8x) exist.
cmake_language(DEFER DIRECTORY "${CMAKE_SOURCE_DIR}" CALL _elastic_link_vendor)

function(_elastic_link_vendor)
    if(NOT DEFINED _supported_php_versions)
        message(FATAL_ERROR "EDOT: _supported_php_versions not defined - upstream CMake may have changed")
    endif()

    foreach(_php_version ${_supported_php_versions})
        set(_target opentelemetry_php_distro_${_php_version})
        if(TARGET ${_target})
            message(STATUS "EDOT: Linking elastic vendor into ${_target}")
            target_link_libraries(${_target}
                PRIVATE $<LINK_LIBRARY:WHOLE_ARCHIVE,elastic>
            )
        else()
            message(WARNING "EDOT: Target ${_target} not found, skipping vendor link")
        endif()
    endforeach()
endfunction()
