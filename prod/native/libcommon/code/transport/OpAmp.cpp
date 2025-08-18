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

#include "OpAmp.h"
#include "common/ProtobufHelper.h"
#include "CommonUtils.h"
#include "ResourceDetector.h"
#include <format>

#include <opentelemetry/semconv/service_attributes.h>
#include <opentelemetry/semconv/deployment_attributes.h>
#include <opamp.pb.h>

using namespace std::literals;

namespace opentelemetry::php::transport {

void OpAmp::init() {
    if (config_->get().opamp_endpoint.empty()) {
        ELOG_DEBUG(log_, OPAMP, "disabled");
        return;
    }

    auto opampHeaders = elasticapm::utils::parseUrlEncodedKeyValueString(config_->get().opamp_headers);
    std::vector<std::pair<std::string_view, std::string_view>> endpointHeaders;
    for (const auto &[k, v] : opampHeaders) {
        endpointHeaders.push_back(std::pair<std::string_view, std::string_view>(k, v));
    }

    std::string endpointUrl = config_->get().opamp_endpoint;

    auto url = elasticapm::utils::parseUrl(endpointUrl);
    if (url.has_value()) {
        if (!url.value().query.has_value()) {
            endpointUrl += "/v1/opamp";
        } else if (url.value().query.value() == "/" || url.value().query.value().empty()) {
            endpointUrl += "v1/opamp";
        }
    } else {
        if (!endpointUrl.ends_with("/v1/opamp")) {
            endpointUrl += "/v1/opamp";
        }
    }

    endpointHash_ = std::hash<std::string>{}(endpointUrl);
    heartbeatInterval_ = {std::chrono::duration_cast<std::chrono::seconds>(config_->get().opamp_heartbeat_interval)};

    ELOG_DEBUG(log_, OPAMP, "Agent UID: '{}', endpoint: '{}', endpoint hash: '{:X}', heartbeat interval: {}ms", boost::uuids::to_string(agentUid_), endpointUrl, endpointHash_, heartbeatInterval_.load());
    for (auto const &[k, v] : endpointHeaders) {
        ELOG_DEBUG(log_, OPAMP, "Header: '{}: {}'", k, v);
    }

    transport_->initializeConnection(endpointUrl, endpointHash_, "application/x-protobuf"s, endpointHeaders, config_->get().opamp_send_timeout, config_->get().opamp_send_max_retries, config_->get().opamp_send_retry_delay);
    startThread();
    try {
        sendInitialAgentToServer();
    } catch (std::exception const &e) {
        ELOG_WARNING(log_, OPAMP, "Unable to send initial message {}", e.what());
    }
}

void OpAmp::handleServerToAgent(const char *data, std::size_t size) {
    opamp::proto::ServerToAgent msg;
    if (!msg.ParseFromArray(data, size)) {
        ELOG_WARNING(log_, OPAMP, "Unable to parse ServerToAgent, data size: {}", size);
        return;
    }

    if (msg.has_agent_identification()) {
        auto agentId = msg.agent_identification().new_instance_uid();
        uint8_t data[16] = {};
        std::memcpy(data, agentId.data(), std::min(sizeof(data), agentId.length()));
        auto newAgentUid = boost::uuids::uuid(data);

        ELOG_DEBUG(log_, OPAMP, "Server updated Agent UID from: '{}' to: '{}'. Data len: {}", boost::uuids::to_string(agentUid_), boost::uuids::to_string(newAgentUid), agentId.length());
        agentUid_ = newAgentUid;
    }

    ELOG_DEBUG(log_, OPAMP, "ServerToAgent capabilities: 0x{:08X}, has_agent_identification: {}, has_command: {}, has_connection_settings: {}, has_custom_capabilities: {}, has_custom_message: {}, has_error_response: {}, has_packages_available: {}, has_remote_config: {}", msg.capabilities(), msg.has_agent_identification(), msg.has_command(), msg.has_connection_settings(), msg.has_custom_capabilities(), msg.has_custom_message(), msg.has_error_response(), msg.has_packages_available(), msg.has_remote_config());

    if (msg.has_connection_settings()) {
        if (msg.connection_settings().has_opamp()) {
            ELOG_DEBUG(log_, OPAMP, "Received connection settings, heartbeat interval {}s", msg.connection_settings().opamp().heartbeat_interval_seconds());
            heartbeatInterval_ = std::chrono::seconds(msg.connection_settings().opamp().heartbeat_interval_seconds());
        }
    }

    if (msg.has_remote_config()) {
        if (msg.remote_config().has_config()) {
            auto remoteHash = msg.remote_config().config_hash();
            std::lock_guard<std::mutex> lock(configAccessMutex_);
            ELOG_DEBUG(log_, OPAMP, "Received remote config hash {}, previous was: {}", remoteHash, currentConfigHash_);
            if (currentConfigHash_ != remoteHash) {
                configFiles_.clear();
                currentConfigHash_ = remoteHash;

                for (auto const &item : msg.remote_config().config().config_map()) {
                    ELOG_DEBUG(log_, OPAMP, "config file: '{}' content type: '{}' body: '{}'", item.first, item.second.content_type(), item.second.body());
                    configFiles_[item.first] = item.second.body();
                }
                configUpdatedWatchers_(configFiles_);
            }
        }
    }

    if (msg.has_custom_capabilities()) {
        auto const &customCapabilities = msg.custom_capabilities();
        ELOG_DEBUG(log_, OPAMP, "Server custom_capabilities count: {}", customCapabilities.capabilities_size());
        for (auto const &capability : customCapabilities.capabilities()) {
            ELOG_DEBUG(log_, OPAMP, "Server custom_capability: {}", capability);
        }
    }

    if (msg.has_error_response()) {
        auto const &error = msg.error_response();
        ELOG_WARNING(log_, OPAMP, "ServerToAgent error '{}', type {}", error.error_message(), static_cast<int>(error.type()));

        if (error.has_retry_info() && error.type() == opamp::proto::ServerErrorResponseType::ServerErrorResponseType_Unavailable) {
            ELOG_WARNING(log_, OPAMP, "ServerToAgent updating retry interval to {}ms", std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::nanoseconds(error.retry_info().retry_after_nanoseconds())).count());
            transport_->updateRetryDelay(endpointHash_, std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::nanoseconds(error.retry_info().retry_after_nanoseconds())));
        }
    }
}

void OpAmp::sendInitialAgentToServer() {
    ::opamp::proto::AgentToServer msg;
    msg.set_instance_uid(reinterpret_cast<const char *>(agentUid_.data()), agentUid_.size());

    auto *desc = msg.mutable_agent_description();
    auto *attrs = desc->mutable_identifying_attributes();

    // get deprecated if exists and send as modern value
    if (auto value = resourceDetector_->get(opentelemetry::semconv::deployment::kDeploymentEnvironment); !value.empty()) {
        common::addKeyValue(attrs, opentelemetry::semconv::deployment::kDeploymentEnvironmentName, value);
    }

    for (auto const &resource : *resourceDetector_) {
        if (!resource.second.empty()) {
            common::addKeyValue(attrs, resource.first, resource.second);
        }
    }

    if (auto value = resourceDetector_->get(opentelemetry::semconv::service::kServiceInstanceId); value.empty()) {
        common::addKeyValue(attrs, opentelemetry::semconv::service::kServiceInstanceId, boost::uuids::to_string(agentUid_));
    }

    msg.set_capabilities(opamp::proto::AgentCapabilities::AgentCapabilities_AcceptsRemoteConfig | opamp::proto::AgentCapabilities::AgentCapabilities_ReportsStatus | opamp::proto::AgentCapabilities::AgentCapabilities_ReportsHeartbeat | opamp::proto::AgentCapabilities::AgentCapabilities_AcceptsOpAMPConnectionSettings | opamp::proto::AgentCapabilities::AgentCapabilities_ReportsRemoteConfig);

    std::string payload;
    if (!msg.SerializeToString(&payload)) {
        throw std::runtime_error("Failed to serialize AgentToServer initial message");
    }

    auto callback = [self = shared_from_this()](int16_t responseCode, std::span<std::byte> data) {
        ELOG_DEBUG(self->log_, OPAMP, "sendInitialAgentToServer response code: {}, data size: {}", responseCode, data.size_bytes());
        self->handleServerToAgent(reinterpret_cast<const char *>(data.data()), data.size_bytes());
    };

    transport_->enqueue(endpointHash_, {reinterpret_cast<std::byte *>(payload.data()), payload.length()}, callback);
}

void OpAmp::sendHeartbeat() {
    ::opamp::proto::AgentToServer msg;

    msg.set_instance_uid(reinterpret_cast<const char *>(agentUid_.data()), agentUid_.size());

    {
        std::lock_guard<std::mutex> lock(configAccessMutex_);
        if (!currentConfigHash_.empty()) {
            auto remoteConfigStatus = msg.mutable_remote_config_status();
            remoteConfigStatus->set_last_remote_config_hash(currentConfigHash_);
            remoteConfigStatus->set_status(opamp::proto::RemoteConfigStatuses::RemoteConfigStatuses_APPLIED);
        }
    }

    std::string payload;
    if (!msg.SerializeToString(&payload)) {
        throw std::runtime_error("Failed to serialize AgentToServer heartbeat message");
    }

    auto callback = [self = shared_from_this()](int16_t responseCode, std::span<std::byte> data) {
        ELOG_DEBUG(self->log_, OPAMP, "sendHeartbeat response code: {} payload size: {}", responseCode, data.size());
        self->handleServerToAgent(reinterpret_cast<const char *>(data.data()), data.size_bytes());
    };
    transport_->enqueue(endpointHash_, {reinterpret_cast<std::byte *>(payload.data()), payload.length()}, callback);
}

} // namespace opentelemetry::php::transport
