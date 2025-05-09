set (_Target  opamp)

file(GLOB_RECURSE OpAmpProtoFiles
     "${_OPENTELEMETRY_OPAMP_PROTO_PATH}/proto/*.proto"
)

protobuf_generate(
    LANGUAGE cpp
    OUT_VAR OTEL_OPAMP_PROTO_SRCS
    IMPORT_DIRS "${_OPENTELEMETRY_OPAMP_PROTO_PATH}/proto"
    PROTOS ${OpAmpProtoFiles}
)

add_library (${_Target}
    STATIC ${SrcFiles} ${OTEL_OPAMP_PROTO_SRCS} ${OTEL_OPAMP_PROTO_HDRS}
)

target_link_libraries(${_Target}
    PUBLIC protobuf::libprotobuf
)

target_include_directories(${_Target} PUBLIC "./"
                                            "${CMAKE_CURRENT_BINARY_DIR}"
                                            )
