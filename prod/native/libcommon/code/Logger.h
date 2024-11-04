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

#include "LoggerInterface.h"
#include "LoggerSinkInterface.h"
#include "LogFeature.h"
#include "CommonUtils.h"
#include "SpinLock.h"

#include <atomic>
#include <memory>
#include <string>
#include <vector>


namespace elasticapm::php {

class LoggerSinkFile : public LoggerSinkInterface {
public:
    LogLevel getLevel() const override;
    void setLevel(LogLevel) override;
    void writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const override;
    bool reopen(std::string fileName);

private:
    std::atomic<LogLevel> level_ = LogLevel::logLevel_off;
    int fd_ = -1;
    std::string openedFilePath_;
    SpinLock spinLock_;
};

class LoggerSinkStdErr : public LoggerSinkInterface {
public:
    LogLevel getLevel() const override;
    void setLevel(LogLevel) override;
    void writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const override;
private:
    std::atomic<LogLevel> level_ = LogLevel::logLevel_off;
};

class LoggerSinkSysLog : public LoggerSinkInterface {
public:
    LogLevel getLevel() const override;
    void setLevel(LogLevel) override;
    void writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const override;
private:
    std::atomic<LogLevel> level_ = LogLevel::logLevel_warning;
};

class Logger : public LoggerInterface {
public:
    Logger(std::vector<std::shared_ptr<LoggerSinkInterface>> sinks) : sinks_(std::move(sinks)) {
    }

    void printf(LogLevel level, const char *format, ...) const override;

    bool doesMeetsLevelCondition(LogLevel level) const override;
    bool doesFeatureMeetsLevelCondition(LogLevel level, LogFeature feature) const override;

    void attachSink(std::shared_ptr<LoggerSinkInterface> sink);

    LogLevel getMaxLogLevel() const override;

    void setLogFeatures(std::unordered_map<elasticapm::php::LogFeature, LogLevel> features) override;

private:
    std::string getFormattedTime() const;
    std::string getFormattedProcessData() const;
    std::vector<std::shared_ptr<LoggerSinkInterface>> sinks_;
    std::unordered_map<elasticapm::php::LogFeature, LogLevel> features_;
    mutable SpinLock spinLock_;
};
}
