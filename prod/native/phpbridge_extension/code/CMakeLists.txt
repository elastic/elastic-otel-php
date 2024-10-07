
#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

foreach(_php_version ${_supported_php_versions})
    set (_Target  phpbridge_ext_${_php_version})

    add_library (${_Target}
        SHARED ${SrcFiles}
        )

    target_compile_definitions(${_Target}
                PRIVATE
                "PHP_ATOM_INC"
                "PHP_ABI=${CMAKE_C_COMPILER_ABI}")

    if(MUSL_BUILD)
        target_compile_definitions(${_Target}
                PRIVATE
                "__ELASTIC_LIBC_MUSL__"
        )
    endif()

    target_include_directories(${_Target} PUBLIC "${php-headers-${_php_version}_INCLUDE_DIRS}"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/ext"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/main"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/TSRM"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/Zend"
                                                "${CONAN_INCLUDE_DIRS_LIBCURL}"
                                                "${CONAN_INCLUDE_DIRS_LIBUNWIND}"
                                            )

    target_link_libraries(${_Target}
        common
        phpbridge_${_php_version}
    )

    set_target_properties(${_Target}
        PROPERTIES OUTPUT_NAME phpbridge_${_php_version}
        PREFIX ""
        DEBUG_SYMBOL_FILE "phpbridge_${_php_version}.debug"
    )

    if (RELEASE_BUILD)
        copy_debug_symbols(${_Target})
    endif()

endforeach()
