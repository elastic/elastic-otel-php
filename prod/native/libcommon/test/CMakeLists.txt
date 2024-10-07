
#scan for source files
#AUX_SOURCE_DIRECTORY(. SrcFiles)
file(GLOB_RECURSE SrcFiles
     "./*.cpp"
)

set(testLib common)

set (targetName  "${testLib}_test")

add_executable(${targetName} ${SrcFiles})

target_link_libraries(${targetName}
    PRIVATE ${testLib}
    PRIVATE CURL::libcurl
    PRIVATE gtest::gtest)



target_include_directories(${targetName}
    PRIVATE "code"
    "${GTest_INCLUDE_DIRS}")


add_test(NAME ${targetName}
    COMMAND ${targetName})
