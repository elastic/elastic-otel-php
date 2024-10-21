
#scan for source files
AUX_SOURCE_DIRECTORY(. SrcFiles)

set (_Target elastic_otel_php_loader)

add_library (${_Target} SHARED ${SrcFiles})

target_link_libraries(${_Target}
    PRIVATE common
)

set_target_properties(${_Target}
    PROPERTIES OUTPUT_NAME elastic_otel_php_loader
    PREFIX ""
    DEBUG_SYMBOL_FILE "elastic_otel_php_loader.debug"
)

if (RELEASE_BUILD)
    copy_debug_symbols(${_Target})
endif()

