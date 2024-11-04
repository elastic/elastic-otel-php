#scan for source files

#AUX_SOURCE_DIRECTORY(. SrcFiles)
file(GLOB_RECURSE SrcFiles
     "./*.cpp"
)

set (_Target  common)

add_library (${_Target}
    STATIC ${SrcFiles}
)

target_link_libraries(${_Target}
    PRIVATE libunwind::libunwind
    PRIVATE CURL::libcurl
)

configure_file("elastic_otel_version.h.in" "${CMAKE_CURRENT_BINARY_DIR}/generated/elastic_otel_version.h")
configure_file("LogFeature.h.in" "${CMAKE_CURRENT_BINARY_DIR}/generated/LogFeature.h")


target_include_directories(${_Target} PUBLIC "./"
                                            "${Boost_INCLUDE_DIRS}"
                                            "${LIBUNWIND_INCLUDE_DIRS}"
                                            "${magic_enum_INCLUDE_DIRS}"
                                            "${CMAKE_CURRENT_BINARY_DIR}/generated/"
                                            Boost::headers
                                            )
