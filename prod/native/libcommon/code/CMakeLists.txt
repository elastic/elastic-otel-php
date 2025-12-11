file(GLOB_RECURSE SrcFiles
     "./*.cpp"
)

set(CoordinatorProtoDir "${CMAKE_CURRENT_SOURCE_DIR}/coordinator/proto")

file(GLOB_RECURSE CoordinatorProtoFiles
     "./coordinator/proto/*.proto"
)

message("Generating protobuf sources from: ${CoordinatorProtoFiles}")
file(MAKE_DIRECTORY "${CMAKE_CURRENT_BINARY_DIR}/coordinator/proto")

protobuf_generate(
    LANGUAGE cpp
    OUT_VAR COORDINATOR_PROTO_SRCS
    IMPORT_DIRS "${CoordinatorProtoDir}"
    PROTOS ${CoordinatorProtoFiles}
    PROTOC_OUT_DIR "${CMAKE_CURRENT_BINARY_DIR}/coordinator/proto"
)

set (_Target  common)

add_library (${_Target}
    STATIC ${SrcFiles}  ${COORDINATOR_PROTO_SRCS} ${COORDINATOR_PROTO_HDRS}
)

target_link_libraries(${_Target}
    PUBLIC protobuf::libprotobuf
    PRIVATE libunwind::libunwind
    PRIVATE CURL::libcurl
    PRIVATE opamp
    PRIVATE semconv
    PRIVATE nlohmann_json::nlohmann_json
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
