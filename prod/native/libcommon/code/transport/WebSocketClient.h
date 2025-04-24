#pragma once

#include "ForkableInterface.h"
#include "ConfigurationStorage.h"
#include "LoggerInterface.h"
#include "WebSocketClientInterface.h"
#include "LoggerInterface.h"

#if defined(BOOST_GCC) && BOOST_GCC >= 40600
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wnon-virtual-dtor"
#endif
#include <boost/beast/core.hpp>
#include <boost/beast/websocket.hpp>
#include <boost/beast/ssl.hpp>
#include <boost/asio/steady_timer.hpp>
#include <boost/asio/io_context.hpp>
#include <boost/asio/strand.hpp>
#include <boost/asio/ssl.hpp>

#if defined(BOOST_GCC) && BOOST_GCC >= 40600
#pragma GCC diagnostic pop
#endif

#include <deque>
#include <functional>
#include <future>
#include <iostream> //TODO remove
#include <magic_enum/magic_enum.hpp>

namespace opentelemetry::php::transport {

namespace beast = boost::beast;
namespace websocket = beast::websocket;
namespace net = boost::asio;
namespace ssl = net::ssl;
using tcp = net::ip::tcp;

using WssStream = websocket::stream<ssl::stream<beast::tcp_stream>>;
using WsStream = websocket::stream<beast::tcp_stream>;

template <typename WebSocketStream>
class WebSocketClient : public std::enable_shared_from_this<WebSocketClient<WebSocketStream>>, public WebSocketClientInterface {
    using Self_t = WebSocketClient<WebSocketStream>;

    using onRead_t = std::function<void(const char *data, std::size_t size)>; // be aware that it will be called in io handler context thread
    using onConnected_t = std::function<void()>;

public:
    WebSocketClient(std::shared_ptr<elasticapm::php::LoggerInterface> log, net::io_context &ioc, onConnected_t onConnected, onRead_t onRead, std::string host, std::string port, std::string path, std::string authHeader, std::string userAgent = "elastic-opamp-php") : log_(std::move(log)), ioc_(ioc), onConnected_(std::move(onConnected)), onRead_(std::move(onRead)), host_(std::move(host)), port_(std::move(port)), path_(std::move(path)), authHeader_(std::move(authHeader)), userAgent_(std::move(userAgent)) {
        if constexpr (std::is_same_v<WebSocketStream, WssStream>) {
            ctx_.emplace(ssl::context::tlsv12_client);
            ctx_->set_verify_mode(ssl::verify_peer);
            ctx_->set_default_verify_paths();
        }
        resetWebSocket();
    }

    ~WebSocketClient() {
        stop();
    }

    void stop() final {
        if (stopped_.exchange(true)) {
            return;
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp::stop");

        timer_.cancel();

        if (ws_ && ws_->is_open()) {
            std::promise<void> closePromise;
            auto closeFuture = closePromise.get_future();

            ws_->async_close(websocket::close_code::normal, [self = this->shared_from_this(), promise = std::move(closePromise)](beast::error_code ec) mutable {
                if (ec) {
                    ELOGF_ERROR(self->log_, OPAMP, "OpAmp::stop closing websocket error. %s", ec.message().c_str());
                }
                ELOGF_DEBUG(self->log_, OPAMP, "OpAmp websocket closed");
                promise.set_value();
            });

            closeFuture.wait();
        }
    }

    void run() {
        stopped_ = false;
        state_ = state_t::connecting;

        if constexpr (std::is_same_v<WebSocketStream, WssStream>) {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp::run hostname verification '" PRsv "'", PRsvArg(host_));

            if (!SSL_set_tlsext_host_name(ws_->next_layer().native_handle(), host_.c_str())) {
                beast::error_code ec{static_cast<int>(::ERR_get_error()), net::error::get_ssl_category()};
                ELOGF_ERROR(log_, OPAMP, "WebSocketClient SSL error '%s'", ec.message().c_str());
                return;
            }
            ws_->next_layer().set_verify_callback(boost::asio::ssl::host_name_verification(host_));
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp::run resolving");
        resolver_.async_resolve(host_, port_, beast::bind_front_handler(&Self_t::onResolve, this->shared_from_this()));
    }

    void send(std::string message) {
        net::post(ws_->get_executor(), std::bind(&Self_t::enqueueAndWrite, this->shared_from_this(), std::move(message)));
    }

    void setHeartbeat(std::chrono::seconds interval, std::string heartbeatPayload) {
        ELOG_DEBUG(log_, OPAMP, "OpAmp::setHeartbeat interval: {}s, payload size: {}", interval.count(), heartbeatPayload.size());
        heartbeatInterval_ = interval;
        heartbeatPayload_ = std::move(heartbeatPayload);
        timer_.cancel();
        if (interval.count() > 0) {
            scheduleHeartbeat();
        }
    }

private:
    void onResolve(beast::error_code ec, tcp::resolver::results_type results) {
        if (ec) {
            return failOnConnection(ec, "resolve");
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp resolved host: '" PRsv "' service: '" PRsv "'", PRsvArg(results->host_name()), PRsvArg(results->service_name()));

        beast::get_lowest_layer(*ws_).expires_after(timeoutResolve_);
        beast::get_lowest_layer(*ws_).async_connect(results, beast::bind_front_handler(&Self_t::onConnect, this->shared_from_this()));
    }

    void onConnect(boost::system::error_code ec, tcp::resolver::results_type::endpoint_type ep) {
        if (ec) {
            return failOnConnection(ec, "connect");
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp connected to '%s' port: %d", ep.address().to_string().c_str(), ep.port());

        beast::get_lowest_layer(*ws_).expires_after(timeoutConnect_);
        hostAndPort_ = host_ + ':' + std::to_string(ep.port());

        if constexpr (std::is_same_v<WebSocketStream, WssStream>) {
            ws_->next_layer().async_handshake(ssl::stream_base::client, beast::bind_front_handler(&Self_t::onSslHandshake, this->shared_from_this()));
        } else {
            onSslHandshake({}); // directly proceed
        }
    }

    void onSslHandshake(beast::error_code ec) {
        if (ec) {
            return failOnConnection(ec, "ssl_handshake");
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp ssl handshake done, setting up ws options");

        beast::get_lowest_layer(*ws_).expires_never();

        auto timeouts = websocket::stream_base::timeout::suggested(beast::role_type::client);
        timeouts.keep_alive_pings = true;
        ws_->set_option(timeouts);
        ws_->set_option(websocket::stream_base::decorator([&](websocket::request_type &req) {
            req.set(beast::http::field::user_agent, userAgent_);
            if (!authHeader_.empty()) {
                req.set(beast::http::field::authorization, authHeader_);
            }
        }));

        ws_->async_handshake(hostAndPort_, path_, beast::bind_front_handler(&Self_t::onWebsocketHandshake, this->shared_from_this()));
    }

    void onWebsocketHandshake(beast::error_code ec) {
        if (ec) {
            return failOnConnection(ec, "ws_handshake");
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp websocket handshake done, starting read and heartbeat");

        state_ = state_t::connected;

        if (onConnected_) {
            onConnected_();
        }

        startRead();
    }

    void startRead() {
        ELOGF_TRACE(log_, OPAMP, "OpAmp::startRead");
        ws_->async_read(buffer_, beast::bind_front_handler(&Self_t::readDone, this->shared_from_this()));
    }

    void readDone(beast::error_code ec, std::size_t bytes_transferred) {
        boost::ignore_unused(bytes_transferred);
        if (ec) {
            return fail(ec, "readDone"sv);
        }

        ELOGF_TRACE(log_, OPAMP, "OpAmp read done, bytes transfered: %zu", bytes_transferred);

        onRead_(static_cast<const char *>(buffer_.data().data()), buffer_.size());
        buffer_.consume(buffer_.size());

        startRead();
    }

    void enqueueAndWrite(std::string message) {
        std::lock_guard<std::mutex> lock(queueMutex_);
        ELOG_DEBUG(log_, OPAMP, "OpAmp::enqueueAndWrite message size: {}, queue size: {}", message.size(), writeQueue_.size());
        writeQueue_.emplace_back(std::move(message));

        if (!writing_) {
            triggerWrite();
        }
    }

    void triggerWrite() {
        // must be executed under lock
        ELOG_DEBUG(log_, OPAMP, "OpAmp::triggerWrite. Connection state: {}, queue size: {}", magic_enum::enum_name(state_), writeQueue_.size());
        if (state_ != state_t::connected) {
            writing_ = false;
            return;
        }

        if (writeQueue_.empty()) {
            writing_ = false;
            return;
        }

        writing_ = true;
        ws_->async_write(net::buffer(writeQueue_.front()), beast::bind_front_handler(&Self_t::handleWrite, this->shared_from_this()));
    }

    void handleWrite(beast::error_code ec, size_t bytesTransferred) {
        std::lock_guard<std::mutex> lock(queueMutex_);
        if (ec) {
            writing_ = false;
            return fail(ec, "handleWrite"sv);
        }
        writeQueue_.pop_front();
        ELOG_DEBUG(log_, OPAMP, "OpAmp::handleWrite. Bytes transferred: {}, connection state: {}, queue size: {}", bytesTransferred, magic_enum::enum_name(state_), writeQueue_.size());
        triggerWrite();
    }

    void scheduleHeartbeat() {
        ELOGF_TRACE(log_, OPAMP, "OpAmp::scheduleHeartbeat interval {}s", heartbeatInterval_.count());
        timer_.expires_after(heartbeatInterval_);
        timer_.async_wait(beast::bind_front_handler(&Self_t::heartbeat, this->shared_from_this()));
    }

    void heartbeat(beast::error_code ec) {
        if (ec) {
            return fail(ec, "heartbeat"sv);
        }

        ELOG_DEBUG(log_, OPAMP, "OpAmp::hearbeat payload size {}", heartbeatPayload_.size());
        send(heartbeatPayload_);
        scheduleHeartbeat();
    }

    void scheduleReconnect() {
        if (stopped_ || state_ != state_t::unconnected) {
            return;
        }
        ELOGF_DEBUG(log_, OPAMP, "OpAmp::reconnect after 5 seconds");

        timer_.expires_after(reconnectAfterPeriod_);
        timer_.async_wait(beast::bind_front_handler(&Self_t::reconnect, this->shared_from_this()));
    }

    void reconnect(beast::error_code ec) {
        if (ec) {
            ELOGF_WARNING(log_, OPAMP, "OpAmp::reconnect failed: %s", ec.what().c_str());
            return;
        }
        if constexpr (std::is_same_v<WebSocketStream, WssStream>) {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp::reconnect setting up SSL");
            ctx_.emplace(ssl::context::tlsv12_client);
            ctx_->set_verify_mode(ssl::verify_peer);
            ctx_->set_default_verify_paths();
        }
        resetWebSocket();
        run();
    }

    void resetWebSocket() {
        if constexpr (std::is_same_v<WebSocketStream, WssStream>) {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp::resetWebSocket reseting SSL websocket");
            ws_ = std::make_unique<WebSocketStream>(net::make_strand(ioc_), *ctx_);
        } else {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp::resetWebSocket reseting websocket");
            ws_ = std::make_unique<WebSocketStream>(net::make_strand(ioc_));
        }
        ws_->binary(true);
        state_ = state_t::unconnected;
    }

    void failOnConnection(beast::error_code ec, const char *what) {
        auto logLevel = ::LogLevel::logLevel_error;
        switch (ec.value()) {
            case beast::errc::operation_canceled:
                logLevel = ::LogLevel::logLevel_debug;
        };

        ELOG(log_, logLevel, OPAMP, "WebSocketClient::failOnConnection in '{}': '{} ({})'. State: {}", what, ec.message(), ec.value(), magic_enum::enum_name(state_));
        state_ = state_t::unconnected;

        if (ec != net::error::netdb_errors::host_not_found) {
            scheduleReconnect();
        }
    }

    void fail(beast::error_code ec, std::string_view what) {
        auto logLevel = ::LogLevel::logLevel_error;
        switch (ec.value()) {
            case beast::errc::operation_canceled:
                logLevel = ::LogLevel::logLevel_debug;
        };
        ELOG(log_, logLevel, OPAMP, "WebSocketClient::fail in '{}': '{} ({})'. State: {}", what, ec.message(), ec.value(), magic_enum::enum_name(state_));

        if (state_ == state_t::connecting) {
            return;
        }

        scheduleReconnect();
    }

    enum state_t { unconnected, connecting, connected };

private:
    std::chrono::seconds reconnectAfterPeriod_{30};
    std::chrono::seconds timeoutResolve_{30};
    std::chrono::seconds timeoutConnect_{30};
    std::chrono::seconds heartbeatInterval_{30};
    std::string heartbeatPayload_;

    std::shared_ptr<elasticapm::php::LoggerInterface> log_;
    net::io_context &ioc_;
    onConnected_t onConnected_;
    onRead_t onRead_;

    std::string host_;
    std::string port_;
    std::string path_;
    std::string hostAndPort_;
    std::string authHeader_;
    std::string userAgent_;

    std::optional<ssl::context> ctx_;
    tcp::resolver resolver_{net::make_strand(ioc_)};
    std::unique_ptr<WebSocketStream> ws_;
    beast::flat_buffer buffer_;
    net::steady_timer timer_{ioc_};
    std::mutex queueMutex_;
    std::deque<std::string> writeQueue_;
    std::atomic_bool writing_ = false;
    std::atomic_bool stopped_ = false;

    state_t state_ = state_t::unconnected;

};

} // namespace opentelemetry::php::transport
