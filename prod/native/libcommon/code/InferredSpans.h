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

#pragma once

#include <atomic>
#include <chrono>
#include <functional>
#include <mutex>
#include <unistd.h>
namespace elasticapm::php {

class InferredSpans {
public:
    using clock_t = std::chrono::steady_clock;
    using time_point_t = std::chrono::time_point<clock_t, std::chrono::milliseconds>;
    using interruptFunc_t = std::function<void()>;
    using attachInferredSpansOnPhp_t = std::function<void(time_point_t interruptRequest, time_point_t now)>;

    InferredSpans(interruptFunc_t interrupt, attachInferredSpansOnPhp_t attachInferredSpansOnPhp) : interrupt_(interrupt), attachInferredSpansOnPhp_(attachInferredSpansOnPhp) {
    }

    void attachBacktraceIfInterrupted() {
        if (phpSideBacktracePending_.load()) { // avoid triggers from agent side with low interval
            return;
        }

        std::unique_lock lock(mutex_);
        time_point_t requestInterruptTime = lastInterruptRequestTick_;

        if (checkAndResetInterruptFlag()) {
            phpSideBacktracePending_ = true;
            lock.unlock();
            attachInferredSpansOnPhp_(requestInterruptTime, std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
            phpSideBacktracePending_ = false;
        }
    }

    void tryRequestInterrupt(time_point_t now) {
        if (interruptedRequested_.load()) {
            return; // it was requested to interrupt in previous interval
        }

        std::unique_lock lock(mutex_);

        if (now > lastInterruptRequestTick_ + samplingInterval_) {
            lastInterruptRequestTick_ = now;

            interruptedRequested_ = true;
            lock.unlock();
            interrupt_(); // set interrupt for user space functions
        }
    }

    void setInterval(std::chrono::milliseconds interval) {
        std::lock_guard lock(mutex_);
        samplingInterval_ = interval;
    }

    void reset() {
        std::lock_guard lock(mutex_);
        lastInterruptRequestTick_ = std::chrono::time_point_cast<time_point_t::duration>(clock_t::now());
    }

private:
    bool checkAndResetInterruptFlag() {
        bool interrupted = true;
        return interruptedRequested_.compare_exchange_strong(interrupted, false, std::memory_order_release, std::memory_order_acquire);
    }

    std::atomic_bool interruptedRequested_ = false;
    std::chrono::milliseconds samplingInterval_ = std::chrono::milliseconds(20);
    time_point_t lastInterruptRequestTick_ = std::chrono::time_point_cast<time_point_t::duration>(clock_t::now());
    std::mutex mutex_;
    interruptFunc_t interrupt_;
    attachInferredSpansOnPhp_t attachInferredSpansOnPhp_;
    std::atomic_bool phpSideBacktracePending_;
};

} // namespace elasticapm::php