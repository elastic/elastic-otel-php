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

#include "WebSocketClient.h"
#include "CommonUtils.h"

#include <opamp.pb.h>
#include <format>

using namespace std::literals;

namespace opentelemetry::php::transport {

void OpAmp::startWebSocketClient(std::string url) {
    ELOGF_TRACE(log_, OPAMP, "OpAmp::startWebSocketClient '%s'", url.c_str());

    auto parsed = elasticapm::utils::parseUrl(url).value_or(elasticapm::utils::ParsedURL{.protocol = "ws"s, .host = "localhost"s, .port = "80"s, .query = "/v1/opamp"s});

    if (!parsed.query.has_value()) {
        parsed.query = "/v1/opamp"s;
    }

    auto onConn = [&]() { onConnected(); };
    auto handleMsg = [&](const char *data, std::size_t size) { handleServerToAgent(data, size); };

    if (parsed.protocol == "wss"sv || parsed.protocol == "https"sv) {
        client_ = std::make_shared<WebSocketClient<WssStream>>(log_, ioContext_, std::move(onConn), std::move(handleMsg), parsed.host, parsed.port.value_or("443"s), parsed.query.value(), "");
    } else if (parsed.protocol == "ws"sv || parsed.protocol == "http"sv) {
        client_ = std::make_shared<WebSocketClient<WsStream>>(log_, ioContext_, std::move(onConn), std::move(handleMsg), parsed.host, parsed.port.value_or("80"), parsed.query.value(), "");
    } else {
        ELOG_ERROR(log_, OPAMP, "OpAmp::unsupported OpAmp protocol in url '{}'", url);
        throw std::runtime_error(std::format("Unsupported OpAmp protocol '{}' in url '{}'", parsed.protocol, url));
    }
    client_->run();
    startThread();
}

void OpAmp::handleServerToAgent(const char *data, std::size_t size) {
    opamp::proto::ServerToAgent msg;
    if (msg.ParseFromArray(data, size)) {
        if (msg.has_connection_settings()) {
            if (msg.connection_settings().has_opamp()) {
                setHeartbeat(std::chrono::seconds(msg.connection_settings().opamp().heartbeat_interval_seconds()));
                // msg.connection_settings().opamp().has_headers()
                // msg.connection_settings().opamp().has_certificate()
            }
        }

        std::cout << "=========== MESSAGE  PARSED\n ";
        std::cout << std::string_view(data, size) << std::endl;
    } else {
        std::cout << "=========== MESSAGE NOT PARSED\n ";
        std::cout << std::string_view(data, size) << std::endl;
    }
}

void OpAmp::onConnected() {
    try {
        sendInitialAgentToServer();
        // TODO read heartbeat interval from OTEL_PHP_OPAMP_HEARTBEAT_INTERVAL
        setHeartbeat(std::chrono::seconds(3));
    } catch (std::exception const &error) {
        ELOG_ERROR(log_, OPAMP, "OpAmp::onConnceted error: {}", error.what());
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

    msg.set_instance_uid("abc123");

    auto *desc = msg.mutable_agent_description();
    auto *attrs = desc->mutable_identifying_attributes();

    addKeyValue(attrs, "service.name", "test");
    addKeyValue(attrs, "service.version", "1.0.0");
    addKeyValue(attrs, "service.instance.id", "abc123");

    addKeyValue(attrs, "service.instance.id", 123);

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

    client_->send(std::move(payload));
}

void OpAmp::setHeartbeat(std::chrono::seconds interval) {
    ::opamp::proto::AgentToServer msg;

    msg.set_instance_uid("abc123");
    std::string payload;
    if (!msg.SerializeToString(&payload)) {
        throw std::runtime_error("Failed to serialize AgentToServer heartbeat message");
    }
    client_->setHeartbeat(interval, std::move(payload));
}

} // namespace opentelemetry::php::transport