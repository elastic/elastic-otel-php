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

#include "CoordinatorMessagesDispatcher.h"
#include "coordinator/proto/CoordinatorCommands.pb.h"

namespace elasticapm::php::coordinator {

void CoordinatorMessagesDispatcher::processRecievedMessage(const std::span<const std::byte> data) {

    coordinator::CoordinatorCommand command;
    if (!command.ParseFromArray(data.data(), data.size())) {
        ELOG_ERROR(logger_, COORDINATOR, "CoordinatorMessagesDispatcher: Failed to parse CoordinatorCommand");
        return;
    }

    switch (command.type()) {
        case coordinator::CoordinatorCommand::ESTABLISH_CONNECTION:
        {
            if (!command.has_establish_connection()) {
                ELOG_ERROR(logger_, COORDINATOR, "CoordinatorMessagesDispatcher: Missing establish_connection payload");
                return;
            }
            const auto &c = command.establish_connection();

            elasticapm::php::transport::HttpEndpointSSLOptions sslOptions;
            if (c.has_ssl_options()) {
                const auto &sslOpts = c.ssl_options();
                sslOptions.insecureSkipVerify = sslOpts.insecure_skip_verify();
                sslOptions.caInfo = sslOpts.ca_info();
                sslOptions.cert = sslOpts.cert();
                sslOptions.certKey = sslOpts.cert_key();
                sslOptions.certKeyPassword = sslOpts.cert_key_password();
            }

            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorMessagesDispatcher: EstablishConnection: url='{}' hash={} content_type='{}' headers={} timeout_ms={} max_retries={} retry_delay_ms={} SSL options[insecure_skip_verify={}, ca_info='{}', cert='{}', cert_key='{}', cert_key_password='{}']", c.endpoint_url(), c.endpoint_hash(), c.content_type(), c.endpoint_headers_size(), c.timeout_ms(), c.max_retries(), c.retry_delay_ms(), sslOptions.insecureSkipVerify, sslOptions.caInfo, sslOptions.cert, sslOptions.certKey, sslOptions.certKeyPassword.empty() ? "" : "<redacted>");

            std::vector<std::pair<std::string_view, std::string_view>> headers;
            for (const auto &h : c.endpoint_headers()) {
                headers.emplace_back(h.first, h.second);
            }

            httpTransport_->initializeConnection(c.endpoint_url(), c.endpoint_hash(), c.content_type(), headers, std::chrono::milliseconds(c.timeout_ms()), c.max_retries(), std::chrono::milliseconds(c.retry_delay_ms()), sslOptions);

            break;
        }
        case coordinator::CoordinatorCommand::SEND_ENDPOINT_PAYLOAD:
        {
            if (!command.has_send_endpoint_payload()) {
                ELOG_ERROR(logger_, COORDINATOR, "CoordinatorMessagesDispatcher: Missing send_endpoint_payload");
                return;
            }
            const auto &p = command.send_endpoint_payload();
            ELOG_DEBUG(logger_, COORDINATOR,
                       "CoordinatorMessagesDispatcher: SendEndpointPayload: hash={} payload_size={}",
                       p.endpoint_hash(),
                       p.payload().size());


            const std::string &raw = p.payload();
            std::span<std::byte> buf(reinterpret_cast<std::byte *>(const_cast<char *>(raw.data())), raw.size());

            httpTransport_->enqueue(p.endpoint_hash(), buf);
            break;
        }
        default:
            ELOG_WARNING(logger_, COORDINATOR, "CoordinatorMessagesDispatcher: Unknown CoordinatorCommand type={}", static_cast<int>(command.type()));
            break;
    }

}

} // namespace elasticapm::php