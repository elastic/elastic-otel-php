#scan for source files

#AUX_SOURCE_DIRECTORY(. SrcFiles)
file(GLOB_RECURSE SrcFiles
     "./*.cpp"
)

set (_Target  common)

file(GLOB_RECURSE OtelProtoFiles
     "opentelemetry/proto/*/*/*.proto" "opentelemetry/proto/*/*/*/*.proto"
)


protobuf_generate_cpp(OTEL_PROTO_SRCS OTEL_PROTO_HDRS ${OtelProtoFiles})



add_library (${_Target}
    STATIC ${SrcFiles} ${OTEL_PROTO_SRCS} ${OTEL_PROTO_HDRS}
)

target_link_libraries(${_Target}
    PRIVATE libunwind::libunwind
    PRIVATE CURL::libcurl
    PUBLIC protobuf::libprotobuf
)

configure_file("elastic_otel_version.h.in" "${CMAKE_CURRENT_BINARY_DIR}/generated/elastic_otel_version.h")
configure_file("LogFeature.h.in" "${CMAKE_CURRENT_BINARY_DIR}/generated/LogFeature.h")

target_include_directories(${_Target} PUBLIC "./"
                                            "${Boost_INCLUDE_DIRS}"
                                            "${LIBUNWIND_INCLUDE_DIRS}"
                                            "${magic_enum_INCLUDE_DIRS}"
                                            "${CMAKE_CURRENT_BINARY_DIR}/generated/"
                                            "${CMAKE_CURRENT_BINARY_DIR}"
                                            Boost::headers
                                            )
