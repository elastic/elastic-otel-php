#pragma once

#include <chrono>
#include <functional>
#include <span>
#include <string_view>
#include <vector>

using namespace std::literals;

namespace elasticapm::php::transport {

class HttpTransportAsyncInterface {
public:
    using responseCallback_t = std::function<void(int16_t responseCode, std::span<std::byte> data)>;
    using enpointHeaders_t = std::vector<std::pair<std::string_view, std::string_view>>;

    virtual ~HttpTransportAsyncInterface() = default;

    virtual void initializeConnection(std::string endpointUrl, std::size_t endpointHash, std::string contentType, enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay) = 0;
    virtual void enqueue(std::size_t endpointHash, std::span<std::byte> payload, responseCallback_t callback = {}) = 0;
};

} // namespace elasticapm::php::transport