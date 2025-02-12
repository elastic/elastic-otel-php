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

#include <functional>
#include <memory>
#include <set>

namespace elasticapm::php {

class LoggerInterface;
class PhpBridgeInterface;

class DependencyAutoLoaderGuard {
public:
    DependencyAutoLoaderGuard(std::shared_ptr<PhpBridgeInterface> bridge, std::shared_ptr<LoggerInterface> logger) : bridge_(std::move(bridge)), logger_(std::move(logger)) {
    }

    void setBootstrapPath(std::string_view bootstrapFilePath);

    void onRequestInit() {
        clear();
    }

    void onRequestShutdown() {
        clear();
    }

    bool shouldDiscardFileCompilation(std::string_view fileName);

private:
    bool wasDeliveredByEDOT(std::string_view fileName) const;

    void clear() {
        lastClass_ = 0;
        lastFunction_ = 0;
        compiledFiles_.clear();
    }

private:
    std::shared_ptr<PhpBridgeInterface> bridge_;
    std::shared_ptr<LoggerInterface> logger_;
    std::set<std::string_view> compiledFiles_; // string_view is safe because we're removing data on request end, they're request scope safe

    std::size_t lastClass_ = 0;
    std::size_t lastFunction_ = 0;

    std::string vendorPath_;
};
} // namespace elasticapm::php