/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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