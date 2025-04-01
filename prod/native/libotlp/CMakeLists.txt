set (_Target  otlp)

file(GLOB_RECURSE OtelProtoFiles
     "${_OPENTELEMETRY_PROTO_PATH}/opentelemetry/proto/*/*/*.proto"
     "${_OPENTELEMETRY_PROTO_PATH}/opentelemetry/proto/*/*/*/*.proto"
)

protobuf_generate(
    LANGUAGE cpp
    OUT_VAR OTEL_PROTO_SRCS
    IMPORT_DIRS "${_OPENTELEMETRY_PROTO_PATH}"
    PROTOS ${OtelProtoFiles}
)

add_library (${_Target}
    STATIC ${SrcFiles} ${OTEL_PROTO_SRCS} ${OTEL_PROTO_HDRS}
)

target_link_libraries(${_Target}
    PUBLIC protobuf::libprotobuf
)

target_include_directories(${_Target} PUBLIC "./"
                                            "${CMAKE_CURRENT_BINARY_DIR}"
                                            )
