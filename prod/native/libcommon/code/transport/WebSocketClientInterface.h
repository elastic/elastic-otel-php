#pragma once

#include <string>

namespace opentelemetry::php::transport {

class WebSocketClientInterface {
public:
    virtual ~WebSocketClientInterface() = default;
    virtual void run() = 0;
    virtual void stop() = 0;
    virtual void send(std::string message) = 0;
    virtual void setHeartbeat(std::chrono::seconds interval, std::string heartbeatPayload) = 0;
};

} // namespace opentelemetry::php::transport