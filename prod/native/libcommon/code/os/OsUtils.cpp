#include "OsUtils.h"
#include <string>
#include <string_view>

#include <unistd.h>
#include <sys/syscall.h>


namespace elasticapm::osutils {

std::string getProcDataToString(const char *fileName) {
    FILE *cmdFile = fopen(fileName, "rb");
    if (!cmdFile) {
        return {};
    }

    unsigned char data;
    std::string output;
    while (fread(&data, 1, 1, cmdFile)) {
        if (data == 0) {
            output.push_back(' ');
        } else {
            output.push_back(data);
        }
    }

    fclose(cmdFile);
    return output;
}

std::string getCommandLine() {
#ifdef _WINDOWS
// TODO implement GetCommandLineW
#else
    return getProcDataToString("/proc/self/cmdline");
#endif
}

std::string getProcessEnvironment() {
#ifdef _WINDOWS
// TODO implement GetCommandLineW
#else
    return getProcDataToString("/proc/self/environ");
#endif
}

pid_t getCurrentProcessId() {
#ifdef _WINDOWS
    return _getpid();
#else
    return getpid();
#endif
}

pid_t getCurrentThreadId() {
#ifdef _WINDOWS
    return (pid_t)GetCurrentThreadId();
#else
    return (pid_t)syscall(SYS_gettid);
#endif
}

pid_t getParentProcessId() {
#ifdef _WINDOWS
    return (pid_t)(-1);
#else
    return getppid();

#endif
}

} // namespace elasticapm::osutils