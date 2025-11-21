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

#include "CoordinatorTelemetrySignalsSender.h"

#include <functional>
#include <memory>
#include <string>

#include "coordinator/proto/CoordinatorCommands.pb.h"

namespace elasticapm::php::coordinator {

void CoordinatorTelemetrySignalsSender::initializeConnection(std::string endpointUrl, std::size_t endpointHash, std::string contentType, enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay, elasticapm::php::transport::HttpEndpointSSLOptions sslOptions) {

    coordinator::EstablishConnectionCommand command;
    command.set_endpoint_url(std::move(endpointUrl));
    command.set_endpoint_hash(endpointHash);
    command.set_content_type(contentType);
    for (const auto &[key, value] : endpointHeaders) {
        (*command.mutable_endpoint_headers())[key] = value;
    }
    command.set_timeout_ms(timeout.count());
    command.set_max_retries(maxRetries);
    command.set_retry_delay_ms(retryDelay.count());

    command.mutable_ssl_options()->set_insecure_skip_verify(sslOptions.insecureSkipVerify);
    command.mutable_ssl_options()->set_ca_info(sslOptions.caInfo);
    command.mutable_ssl_options()->set_cert(sslOptions.cert);
    command.mutable_ssl_options()->set_cert_key(sslOptions.certKey);
    command.mutable_ssl_options()->set_cert_key_password(sslOptions.certKeyPassword);

    coordinator::CoordinatorCommand coordCommand;
    coordCommand.set_type(coordinator::CoordinatorCommand::ESTABLISH_CONNECTION);
    *coordCommand.mutable_establish_connection() = command;

    std::string serializedCommand;
    if (!coordCommand.SerializeToString(&serializedCommand)) {
        if (logger_) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorTelemetrySignalsSender: failed to serialize EstablishConnectionCommand");
        }
        return;
    }

    if (!sendPayload_(serializedCommand)) {
        ELOG_WARNING(logger_, COORDINATOR, "CoordinatorTelemetrySignalsSender: failed to send EstablishConnectionCommand, endpoint hash: {}", endpointHash);
    }
}

void CoordinatorTelemetrySignalsSender::enqueue(uint64_t endpointHash, std::span<std::byte> payload, responseCallback_t callback) {
    coordinator::SendEndpointPayloadCommand command;
    command.set_endpoint_hash(endpointHash);
    command.set_payload(payload.data(), payload.size());

    coordinator::CoordinatorCommand coordCommand;
    coordCommand.set_type(coordinator::CoordinatorCommand::SEND_ENDPOINT_PAYLOAD);
    *coordCommand.mutable_send_endpoint_payload() = command;

    std::string serializedCommand;
    if (!coordCommand.SerializeToString(&serializedCommand)) {
        if (logger_) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorSender: failed to serialize SendEndpointDataCommand");
        }
        return;
    }

    if (!sendPayload_(serializedCommand)) {
        ELOG_WARNING(logger_, COORDINATOR, "CoordinatorTelemetrySignalsSender: Dropping payload. Endpoint hash: %zu, payload size: {}", endpointHash, payload.size());
    }
}

} // namespace elasticapm::php
