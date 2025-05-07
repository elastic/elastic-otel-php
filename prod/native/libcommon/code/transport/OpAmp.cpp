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

// #include "WebSocketClient.h"
// #include "CommonUtils.h"

#include <opamp.pb.h>

#include <format>

using namespace std::literals;

namespace opentelemetry::php::transport {

void OpAmp::init(std::string endpointUrl, std::vector<std::pair<std::string_view, std::string_view>> const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay) {
    ELOG_DEBUG(log_, OPAMP, "Agent UID: {}", boost::uuids::to_string(agentUid_));

    endpointHash_ = std::hash<std::string>{}(endpointUrl);
    transport_->initializeConnection(endpointUrl, endpointHash_, "application/x-protobuf"s, endpointHeaders, timeout, maxRetries, retryDelay);
    startThread();
    sendInitialAgentToServer();
}

void OpAmp::handleServerToAgent(const char *data, std::size_t size) {
    opamp::proto::ServerToAgent msg;
    if (!msg.ParseFromArray(data, size)) {
        ELOG_WARNING(log_, OPAMP, "Unable to parse ServerToAgent, data size: {}", size);
        return;
    }

    if (msg.has_connection_settings()) {
        if (msg.connection_settings().has_opamp()) {
            ELOG_DEBUG(log_, OPAMP, "Received connection settings, heartbeat interval {}s", msg.connection_settings().opamp().heartbeat_interval_seconds());
            heartbeatInterval_ = std::chrono::seconds(msg.connection_settings().opamp().heartbeat_interval_seconds());
        }
    }

    if (msg.has_remote_config()) {
        if (msg.remote_config().has_config()) {
            auto remoteHash = msg.remote_config().config_hash();
            ELOG_DEBUG(log_, OPAMP, "Received remote config hash {}, previous was: {}", remoteHash, currentConfigHash_);
            if (currentConfigHash_ != remoteHash) {
                configFiles_.clear();
                currentConfigHash_ = remoteHash;

                for (auto const &item : msg.remote_config().config().config_map()) {
                    ELOG_DEBUG(log_, OPAMP, "config file: '{}' content type: '{}' body: '{}'", item.first, item.second.content_type(), item.second.body());
                    configFiles_[item.first] = item.second.body();
                }
            }
        }
    }
}

template <typename KeyValue, typename ValueType>
void addKeyValue(google::protobuf::RepeatedPtrField<KeyValue> *map, std::string key, ValueType const &value) {
    auto kv = map->Add();
    kv->set_key(std::move(key));
    auto val = kv->mutable_value();
    if constexpr (std::is_same_v<decltype(value), bool>) {
        val->set_bool_value(value);
    } else if constexpr (std::is_floating_point_v<std::remove_reference_t<decltype(value)>>) {
        val->set_double_value(value);
    } else if constexpr (!std::is_null_pointer_v<std::remove_reference_t<decltype(value)>> && std::is_convertible_v<decltype(value), std::string_view>) {
        val->set_string_value(value);
    } else {
        val->set_int_value(value);
    }
}

void OpAmp::sendInitialAgentToServer() {
    ::opamp::proto::AgentToServer msg;
    msg.set_instance_uid(reinterpret_cast<const char *>(agentUid_.data()), agentUid_.size());

    auto *desc = msg.mutable_agent_description();
    auto *attrs = desc->mutable_identifying_attributes();

    addKeyValue(attrs, "service.name", "test");
    addKeyValue(attrs, "service.version", "1.0.0");
    addKeyValue(attrs, "service.instance.id", "abc123");

    // os.type, os.version - to describe where the Agent runs.
    // host.* to describe the host the Agent runs on.
    // cloud.* to describe the cloud where the host is located.
    // any other relevant Resource attributes that describe this Agent and the environment it runs in.
    // any user-defined attributes that the end user would like to associate with this Agent.

    // service.name should be set to the same value that the Agent uses in its own telemetry.
    // service.namespace if it is used in the environment where the Agent runs.
    // service.version should be set to version number of the Agent build.
    // service.instance.id should be set. It may be set equal to the Agent’s instance uid (equal to ServerToAgent.instance_uid field) or any other value that uniquely identifies the Agent in combination with other attributes.
    // any other attributes that are necessary for uniquely identifying the Agent’s own telemetry.

    // TODO control heartbeat by reading OTEL_OPAMP_DISABLE_HEARTBEATS...

    msg.set_capabilities(opamp::proto::AgentCapabilities::AgentCapabilities_AcceptsRemoteConfig | opamp::proto::AgentCapabilities::AgentCapabilities_ReportsStatus | opamp::proto::AgentCapabilities::AgentCapabilities_ReportsHeartbeat);

    std::string payload;
    if (!msg.SerializeToString(&payload)) {
        throw std::runtime_error("Failed to serialize AgentToServer message");
    }

    auto callback = [self = shared_from_this()](int16_t responseCode, std::span<std::byte> data) {
        self->handleServerToAgent(reinterpret_cast<const char *>(data.data()), data.size_bytes());
        std::cout << "== code: " << (int)responseCode << "======= size:" << data.size() << "================\n" << reinterpret_cast<const char *>(data.data()) << "\n==================\n";
    };

    transport_->enqueue(endpointHash_, {reinterpret_cast<std::byte *>(payload.data()), payload.length()}, callback);
}

void OpAmp::sendHeartbeat() {
    ::opamp::proto::AgentToServer msg;

    msg.set_instance_uid(reinterpret_cast<const char *>(agentUid_.data()), agentUid_.size());
    std::string payload;
    if (!msg.SerializeToString(&payload)) {
        throw std::runtime_error("Failed to serialize AgentToServer message");
    }

    auto callback = [self = shared_from_this()](int16_t responseCode, std::span<std::byte> data) {
        ELOG_DEBUG(self->log_, OPAMP, "heartbeat response code: {} payload size: {}", responseCode, data.size());
        self->handleServerToAgent(reinterpret_cast<const char *>(data.data()), data.size_bytes());
    };
    transport_->enqueue(endpointHash_, {reinterpret_cast<std::byte *>(payload.data()), payload.length()}, callback);
}

} // namespace opentelemetry::php::transport