
function(elastic_read_properties PROPERTIES_FILENAME PROPERTIES_PREFIX)
    message(STATUS "Reading properties from: ${PROPERTIES_FILENAME}")
    file(STRINGS ${PROPERTIES_FILENAME} _ELASTIC_PROJECT_PROPERTIES)

    foreach(line IN LISTS _ELASTIC_PROJECT_PROPERTIES)
        if(line MATCHES "^([^=]+)=(.*)$")
            set(key "${CMAKE_MATCH_1}")
            set(value "${CMAKE_MATCH_2}")

            string(TOUPPER "${key}" key_upper)
            set(var_name "${PROPERTIES_PREFIX}${key_upper}")

            set(${var_name} "${value}" PARENT_SCOPE)

            message(STATUS "${var_name} = ${value}")
        endif()
    endforeach()
endfunction()

# convert bash-style array to list, like: (el el el)
function(elastic_array_to_list INPUT_STRING OUTPUT_LIST)
    string(REPLACE "(" "" TEMP_STRING ${INPUT_STRING})
    string(REPLACE ")" "" TEMP_STRING ${TEMP_STRING})
    string(STRIP ${TEMP_STRING} TEMP_STRING)

    separate_arguments(TEMP_LIST NATIVE_COMMAND "${TEMP_STRING}")

    set(${OUTPUT_LIST} ${TEMP_LIST} PARENT_SCOPE)
endfunction()
