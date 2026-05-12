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

#include "VendorCustomizationsInterface.h"

#include <map>
#include <memory>
#include <string>

namespace elastic::otel {

class ElasticConfigProvider;

class ElasticVendor : public opentelemetry::php::VendorCustomizationsInterface {
public:
    std::string getVendorName() const override;
    std::string getDistributionName() const override;
    std::string getDistributionVersion() const override;
    std::string getUserAgent() const override;

    std::pair<int, std::shared_ptr<opentelemetry::php::config::OptionValueProviderInterface>> getOptionValueProvider() override;
    void setLogger(std::shared_ptr<opentelemetry::php::LoggerInterface> logger) override;
    std::map<std::string, std::string> getAdditionalResourceAttributes() override;

private:
    std::shared_ptr<ElasticConfigProvider> configProvider_;
};

} // namespace elastic::otel
