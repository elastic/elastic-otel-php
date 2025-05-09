set (_Target  semconv)


set(SCHEMA_HEADER "${CMAKE_CURRENT_SOURCE_DIR}/include/opentelemetry/semconv/schema_url.h")
set(EXPECTED_VERSION "${_PROJECT_PROPERTIES_OTEL_SEMCONV_VERSION}")

file(READ "${SCHEMA_HEADER}" SCHEMA_CONTENT)

string(REGEX MATCH "schemas/v([0-9]+\\.[0-9]+\\.[0-9]+)" _ UNUSED "${SCHEMA_CONTENT}")
set(ACTUAL_VERSION "${CMAKE_MATCH_1}")

if(NOT ACTUAL_VERSION STREQUAL EXPECTED_VERSION)
    message(FATAL_ERROR "Semantic convention schema version mismatch: expected ${EXPECTED_VERSION}, but found ${ACTUAL_VERSION}.\nRun ./tools/build/generate_semconv.sh from repo root to regenerate files.")
endif()

add_library (${_Target} INTERFACE)

target_compile_definitions(${_Target} INTERFACE OPENTELEMETRY_DEPRECATED=/*deprecated*/)



target_include_directories(${_Target}
    INTERFACE
        $<BUILD_INTERFACE:${CMAKE_CURRENT_SOURCE_DIR}/include>
)
