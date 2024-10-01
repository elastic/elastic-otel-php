#scan for source files

#AUX_SOURCE_DIRECTORY(. SrcFiles)
file(GLOB_RECURSE SrcFiles
     "./*.cpp"
)



set (_Target  libcommon)

add_library (${_Target}
    STATIC ${SrcFiles}
)

target_link_libraries(${_Target}
    PRIVATE CONAN_PKG::libunwind
    PRIVATE CONAN_PKG::libcurl

)

configure_file("elastic_otel_version.h.in" "${CMAKE_CURRENT_BINARY_DIR}/generated/elastic_otel_version.h")

target_include_directories(${_Target} PUBLIC "./"
                                            "${CONAN_INCLUDE_DIRS_BOOST}"
                                            "${CONAN_INCLUDE_DIRS_LIBUNWIND}"
                                            "${CMAKE_CURRENT_BINARY_DIR}/generated/"
                                            )

