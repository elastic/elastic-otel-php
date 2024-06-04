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


#include "StackTraceCapture.h"
#include "CommonUtils.h"
#include "os/OsUtils.h"
#include <cstring>

#ifndef __USE_GNU
#define __USE_GNU 1
#endif
#include <dlfcn.h>
#include <libunwind.h>

namespace elasticapm::osutils {

std::string getStackTrace(size_t numberOfFramesToSkip) {
    unw_cursor_t unwindCursor;
    unw_context_t unwindContext;
    constexpr size_t funcNameBufferSize = 100;
    char funcNameBuffer[funcNameBufferSize];
    unw_word_t offsetInsideFunc;

    if (unw_getcontext(&unwindContext) < 0) {
        return {};
    }
    if (unw_init_local(&unwindCursor, &unwindContext) < 0) {
        return {};
    }

    std::string output;
    for (size_t frameIndex = 0;; ++frameIndex) {
        // +1 is for this function frame
        if (frameIndex >= numberOfFramesToSkip + 1) {
            unw_proc_info_t pi;
            if (unw_get_proc_info(&unwindCursor, &pi) == 0) {
                *funcNameBuffer = 0;
                offsetInsideFunc = 0;
                int getProcNameRetVal = unw_get_proc_name(&unwindCursor, funcNameBuffer, funcNameBufferSize, &offsetInsideFunc);
                if (getProcNameRetVal != UNW_ESUCCESS && getProcNameRetVal != -UNW_ENOMEM) {
                    strcpy(funcNameBuffer, "???");
                    unw_word_t pc;
                    unw_get_reg(&unwindCursor, UNW_REG_IP, &pc);
                    offsetInsideFunc = pc - pi.start_ip;
                }

                Dl_info dlInfo;
                if (dladdr((const void *)pi.gp, &dlInfo)) {
                    output.append(elasticapm::utils::stringPrintf("%s(%s+0x%lx) ModuleBase: %p FuncStart: 0x%lx FuncEnd: 0x%lx FuncStartRelative: 0x%lx FuncOffsetRelative: 0x%lx\n\t'addr2line -afCp -e \"%s\" %lx'\n", dlInfo.dli_fname ? dlInfo.dli_fname : "???", dlInfo.dli_sname ? dlInfo.dli_sname : funcNameBuffer, offsetInsideFunc, dlInfo.dli_fbase, pi.start_ip, pi.end_ip, pi.start_ip - reinterpret_cast<unw_word_t>(dlInfo.dli_fbase), pi.start_ip - reinterpret_cast<unw_word_t>(dlInfo.dli_fbase) + offsetInsideFunc, dlInfo.dli_fname ? dlInfo.dli_fname : "???", pi.start_ip - reinterpret_cast<unw_word_t>(dlInfo.dli_fbase) + offsetInsideFunc));
                } else {
                    output.append(elasticapm::utils::stringPrintf("dladdr failed on frame %zu\n", frameIndex));
                }
            } else {
                output.append(elasticapm::utils::stringPrintf("unw_get_proc_info failed on frame %zu", frameIndex));
            }
        }

        int unwindStepRetVal = 0;
        unwindStepRetVal = unw_step(&unwindCursor);
        if (unwindStepRetVal < 0) {
            return output;
        } else if (unwindStepRetVal == 0) {
            break;
        }
    }
    return output;
}

}