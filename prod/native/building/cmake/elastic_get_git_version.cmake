
find_package(Git)

function(elastic_get_git_version PROJECT_VERSION SIMPLE_VERSION GIT_REVISION_HASH)
    #PROJECT_VERSION MAJOR.MINOR.PATCH
    #SIMPLE_VERSION MAJOR.MINOR.PATCH-dirty

    block(SCOPE_FOR VARIABLES)
    execute_process(COMMAND ${GIT_EXECUTABLE} describe --tags --match "v*" --dirty --abbrev=0
        WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
        OUTPUT_VARIABLE GIT_VERSION
        RESULT_VARIABLE GIT_RESULT
        OUTPUT_STRIP_TRAILING_WHITESPACE
    )

    if(${GIT_RESULT})
        message(FATAL_ERROR "Could not get version from git. Git error code: ${GIT_RESULT}. Output: ${GIT_VERSION}")
    endif()

    # strip leading v
    string(SUBSTRING ${GIT_VERSION} 1 -1 TMP_SIMPLE_VERSION)

    # remove -dirty if exists
    string(REPLACE "-dirty" "" TMP_PROJECT_VERSION ${TMP_SIMPLE_VERSION})

    execute_process(COMMAND ${GIT_EXECUTABLE} rev-parse HEAD
        WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
        OUTPUT_VARIABLE GIT_REVISION
        RESULT_VARIABLE GIT_RESULT
        OUTPUT_STRIP_TRAILING_WHITESPACE
    )

    if(${GIT_RESULT})
        message(FATAL_ERROR "Could not get revision from git. Git error code: ${GIT_RESULT}. Output: ${GIT_REVISION}")
    endif()
    set(${GIT_REVISION_HASH} ${GIT_REVISION})

    set(${PROJECT_VERSION} ${TMP_PROJECT_VERSION})
    set(${SIMPLE_VERSION} ${TMP_SIMPLE_VERSION})

    return(PROPAGATE ${PROJECT_VERSION} ${SIMPLE_VERSION} ${GIT_REVISION_HASH})
    endblock()
endfunction()

function(elastic_git_get_hash OUTPUT_HASH)
    block(SCOPE_FOR VARIABLES)
    set(IS_DIRTY false)

    execute_process(COMMAND ${GIT_EXECUTABLE} diff-index --quiet  HEAD --
        WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
        RESULT_VARIABLE GIT_RESULT
    )

    if(${GIT_RESULT})
        set(IS_DIRTY true)
    endif()

    execute_process(COMMAND ${GIT_EXECUTABLE} rev-parse --short HEAD
        WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
        OUTPUT_VARIABLE GIT_VERSION
        RESULT_VARIABLE GIT_RESULT
        OUTPUT_STRIP_TRAILING_WHITESPACE
    )

    if(${GIT_RESULT})
        message(FATAL_ERROR "Could not get revision from git. Git error code: ${GIT_RESULT}. Output: ${GIT_VERSION}")
    endif()

    if(IS_DIRTY)
        set(TMP_OUTPUT_HASH "${GIT_VERSION}-dirty")
    else()
        set(TMP_OUTPUT_HASH "${GIT_VERSION}")
    endif()
    # message(STATUS "elastic_git_get_hash = ${TMP_OUTPUT_HASH}")
    set(${OUTPUT_HASH} "${TMP_OUTPUT_HASH}")
    return(PROPAGATE ${OUTPUT_HASH})
    endblock()

endfunction()