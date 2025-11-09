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

#include "DependencyAutoLoaderGuard.h"
#include "LoggerInterface.h"
#include "PhpBridgeInterface.h"

#include <functional>
#include <filesystem>
#include <format>
#include <set>

namespace elasticapm::php {
using namespace std::string_view_literals;

void DependencyAutoLoaderGuard::setBootstrapPath(std::string_view bootstrapFilePath) {
    auto [major, minor] = bridge_->getPhpVersionMajorMinor();
    auto path = std::filesystem::path(bootstrapFilePath).parent_path();
	path /= std::filesystem::path("vendor_per_PHP_version") / std::format("{}{}", major, minor);
    vendorPath_ = path.c_str();
    ELOGF_DEBUG(logger_, DEPGUARD, "vendor path set to: " PRsv, PRsvArg(vendorPath_));
}

bool DependencyAutoLoaderGuard::shouldDiscardFileCompilation(std::string_view fileName) {
    try {
        std::string compiledFilePath = std::filesystem::exists(fileName) ? std::filesystem::canonical(fileName) : fileName;

        if (compiledFilePath.starts_with(vendorPath_)) {
            return false;
        }

        auto [lastClass, lastFunction] = bridge_->getNewlyCompiledFiles(
            [this](std::string_view name) {
                // storing only dependencies delivered by EDOT
                if (name.starts_with(vendorPath_)) {
                    if (name.substr(vendorPath_.length()).starts_with("/composer/")) { // skip compsoer files - they must be compiled
                        ELOGF_TRACE(logger_, DEPGUARD, "Skipping storage of composer files: " PRsv, PRsvArg(name));
                        return;
                    }
                    compiledFiles_.insert(name);
                    ELOGF_TRACE(logger_, DEPGUARD, "Storing file: " PRsv, PRsvArg(name));
                }
            },
            lastClass_, lastFunction_);

        lastClass_ = lastClass;
        lastFunction_ = lastFunction;

        if (wasDeliveredByEDOT(compiledFilePath)) {
            ELOGF_DEBUG(logger_, DEPGUARD, "Compilation of file '" PRsv "' will be discarded", PRsvArg(compiledFilePath));
            return true;
        }

    } catch (std::exception const &e) {
        ELOGF_WARNING(logger_, DEPGUARD, "shouldDiscardFileCompilation of file '" PRsv "' throwed: %s", PRsvArg(fileName), e.what());
        return false;
    }

    return false;
}

bool DependencyAutoLoaderGuard::wasDeliveredByEDOT(std::string_view fileName) const {
    constexpr std::string_view vendor = "/vendor/"sv;

    auto vendorPos = fileName.find(vendor);
    if (vendorPos == std::string_view::npos) {
        return false;
    }

    auto afterVendor = fileName.substr(vendorPos + vendor.size());

    auto found = std::find_if(std::begin(compiledFiles_), std::end(compiledFiles_), [afterVendor, bootstrapLen = vendorPath_.length()](std::string_view storedFile) -> bool {
        std::string_view fileView = storedFile.substr(bootstrapLen + 1); // add 1 for slash
        if (fileView == afterVendor) {
            return true;
        }
        return false;
    });

    if (found != std::end(compiledFiles_)) {
        return true;
    }
    return false;
}

} // namespace elasticapm::php