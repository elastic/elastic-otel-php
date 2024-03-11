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
)

target_include_directories(${_Target} PUBLIC "./"
                                            "${CONAN_INCLUDE_DIRS_BOOST}"
                                            "${CONAN_INCLUDE_DIRS_LIBUNWIND}"

                                            )

