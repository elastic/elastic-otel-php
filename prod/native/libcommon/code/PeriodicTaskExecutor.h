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

#include "ForkableInterface.h"

#include <atomic>
#include <condition_variable>
#include <functional>
#include <stop_token>
#include <thread>
#include <vector>

namespace elasticapm::php {


class PeriodicTaskExecutor : public ForkableInterface {
private:
    auto getThreadWorkerFunction() {
        return [this]() { work(); };
    }

public:
	using clock_t = std::chrono::steady_clock;
	using time_point_t = std::chrono::time_point<clock_t, std::chrono::milliseconds>;

    using task_t = std::function<void(time_point_t)>;
    using worker_init_t = std::function<void()>;

    PeriodicTaskExecutor(std::vector<task_t> periodicTasks, worker_init_t workerInit = {}) : periodicTasks_(), workerInit_(std::move(workerInit)), thread_() {
        for (auto &task : periodicTasks) {
            periodicTasks_.push_back(std::make_shared<task_t>(std::move(task)));
        }
        thread_ = std::thread(getThreadWorkerFunction());
    }

    ~PeriodicTaskExecutor() {
        shutdown();

        if (thread_.joinable()) {
            thread_.join();
        }
    }

    void addTask(task_t task) {
        std::lock_guard<std::mutex> lock(mutex_);
        periodicTasks_.push_back(std::make_shared<task_t>(std::move(task)));
    }

    void work() {
        if (workerInit_) {
            workerInit_();
        }

        std::unique_lock<std::mutex> lock(mutex_);
        while(working_) {
            pauseCondition_.wait(lock, [this]() -> bool {
                return resumed_ || !working_;
            });

            if (!working_) {
                break;
            }

            lock.unlock();
            std::this_thread::sleep_for(sleepInterval_);

            std::vector<std::shared_ptr<task_t>> tasksSnapshot;
            {
                std::lock_guard<std::mutex> lock(mutex_);
                tasksSnapshot = periodicTasks_;
            }

            for (auto const &task : tasksSnapshot) {
                (*task)(std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
            }
            lock.lock();
        }
    }

    void prefork() final {
        shutdown();
        if (thread_.joinable()) {
            thread_.join();
        }
    }

    void postfork([[maybe_unused]] bool child) final {
        working_ = true;
        mutex_.lock();
        thread_ = std::thread(getThreadWorkerFunction());
        mutex_.unlock();
        pauseCondition_.notify_all();
    }

    void resumePeriodicTasks() {
        {
        std::lock_guard<std::mutex> lock(mutex_);
        resumed_ = true;
        }
        pauseCondition_.notify_all();
    }
    void suspendPeriodicTasks() {
        {
       	std::lock_guard<std::mutex> lock(mutex_);
        resumed_ = false;
        }
        pauseCondition_.notify_all();
    }

    void setInterval(std::chrono::milliseconds interval) {
        sleepInterval_ = interval;
    }

private:
   PeriodicTaskExecutor(const PeriodicTaskExecutor&) = delete;
   PeriodicTaskExecutor& operator=(const PeriodicTaskExecutor&) = delete;

   void shutdown() {
        {
        std::lock_guard<std::mutex> lock(mutex_);
        working_ = false;
        }
        pauseCondition_.notify_all();
   }

private:

    std::chrono::milliseconds sleepInterval_ = std::chrono::milliseconds(20);
    std::vector<std::shared_ptr<task_t>> periodicTasks_;
    worker_init_t workerInit_;
    std::mutex mutex_;
    std::thread thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    bool resumed_ = false;
};


}
