# AUX_SOURCE_DIRECTORY(. SrcFiles)

# set (_Target  libphpbridge)


# add_library (${_Target}
#     STATIC ${SrcFiles}
# )




#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

foreach(_php_version ${_supported_php_versions})
    set (_Target  phpbridge_${_php_version})

    add_library (${_Target}
        STATIC ${SrcFiles}
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


    target_include_directories(${_Target} PUBLIC "./")



    target_include_directories(${_Target} PRIVATE "${php-headers-${_php_version}_INCLUDE_DIRS}"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/ext"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/main"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/TSRM"
                                                "${php-headers-${_php_version}_INCLUDE_DIRS}/Zend"
                                            )

    target_link_libraries(${_Target}
        PRIVATE common
        PUBLIC otlp
    )

endforeach()
