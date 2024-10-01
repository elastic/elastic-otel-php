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

#include "ForkHandler.h"

#ifndef _WINDOWS
#include <pthread.h>
#include <errno.h>

#include "os/OsUtils.h"
#include "LoggerInterface.h"
#include "ModuleGlobals.h"
#include "PeriodicTaskExecutor.h"
#include "transport/HttpTransportAsync.h"

static void callbackToLogForkBeforeInParent() {
    ELOG_DEBUG(EAPM_GL(logger_), "Before process fork (i.e., in parent context); its parent (i.e., grandparent) PID: %d", static_cast<int>(elasticapm::osutils::getParentProcessId()));
    // TODO implement forkable registry
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->periodicTaskExecutor_) {
        ELASTICAPM_G(globals)->periodicTaskExecutor_->prefork();
    }
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->httpTransportAsync_) {
        ELASTICAPM_G(globals)->httpTransportAsync_->prefork();
    }
}

static void callbackToLogForkAfterInParent() {
    ELOG_DEBUG(EAPM_GL(logger_), "After process fork (in parent context)");
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->periodicTaskExecutor_) {
        ELASTICAPM_G(globals)->periodicTaskExecutor_->postfork(false);
    }
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->httpTransportAsync_) {
        ELASTICAPM_G(globals)->httpTransportAsync_->postfork(false);
    }
}

static void callbackToLogForkAfterInChild() {
    ELOG_DEBUG(EAPM_GL(logger_), "After process fork (in child context); parent PID: %d", static_cast<int>(elasticapm::osutils::getParentProcessId()));
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->periodicTaskExecutor_) {
        ELASTICAPM_G(globals)->periodicTaskExecutor_->postfork(true);
    }
    if (ELASTICAPM_G(globals) && ELASTICAPM_G(globals)->httpTransportAsync_) {
        ELASTICAPM_G(globals)->httpTransportAsync_->postfork(true);
    }
}

void registerCallbacksToLogFork() {
    int retVal = pthread_atfork(callbackToLogForkBeforeInParent, callbackToLogForkAfterInParent, callbackToLogForkAfterInChild);
    if (retVal == 0) {
        ELOG_DEBUG(EAPM_GL(logger_), "Registered callbacks to log process fork");
    } else {
        ELOG_WARNING(EAPM_GL(logger_), "Failed to register callbacks to log process fork; return value: %d", retVal);
    }
}

#else
void registerCallbacksToLogFork() {
}
#endif