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

#include "elastic_otel_version.h"
#include "LoggerInterface.h"
#include "ModuleGlobals.h"
#include "CommonUtils.h"
#include "os/StackTraceCapture.h"


#include <signal.h>

namespace signalHandlerData {
typedef void (*OsSignalHandler)(int);
static OsSignalHandler oldSigSegvHandler = nullptr;
} // namespace signalHandlerData

void SigSegvHandler(int signalId) {
#ifdef __ELASTIC_LIBC_MUSL__
#define LIBC_IMPL "musl"
#else
#define LIBC_IMPL "glibc"
#endif

    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->logger_) {
        auto output = elasticapm::osutils::getStackTrace(0);
        ELOGF_NF_CRITICAL(ELASTICAPM_G(globals)->logger_.get(), "Received signal %d. Agent version: " ELASTIC_OTEL_VERSION " " LIBC_IMPL "\n%s", signalId, output.c_str());

        /* Call the default signal handler to have core dump generated... */
        if (signalHandlerData::oldSigSegvHandler) {
            signal(signalId, signalHandlerData::oldSigSegvHandler);
        } else {
            signal(signalId, SIG_DFL);
        }
        raise(signalId);
    }
}

void registerSigSegvHandler(elasticapm::php::LoggerInterface * logger) {
    auto retval = signal(SIGSEGV, SigSegvHandler);
    if (retval == SIG_ERR) {
        ELOGF_NF_ERROR(logger, "Unable to set SIGSEGV handler. Errno: %d", errno);
        return;
    } else {
        signalHandlerData::oldSigSegvHandler = retval;
    }
}

void unregisterSigSegvHandler() {
    if (signalHandlerData::oldSigSegvHandler == SigSegvHandler) {
        signal(SIGSEGV, signalHandlerData::oldSigSegvHandler);
        signalHandlerData::oldSigSegvHandler = nullptr;
    }
}