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

#include "LogFeature.h"
#include <magic_enum.hpp>
#include <stdexcept>
#include <string>
#include <string_view>

namespace elasticapm::php {

[[nodiscard]] LogFeature parseLogFeature(std::string_view featureName) {
    using namespace std::string_literals;
    auto feature = magic_enum::enum_cast<LogFeature>(featureName, magic_enum::case_insensitive);
    if (!feature.has_value()) {
        throw std::invalid_argument("Unknown log feature: "s + std::string(featureName));
    }
    return feature.value();
}

[[nodiscard]] std::string_view getLogFeatureName(LogFeature feature) {
    return magic_enum::enum_name(feature);
}

} // namespace elasticapm::php
