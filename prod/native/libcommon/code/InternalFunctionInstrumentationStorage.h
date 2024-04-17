#pragma once

#include <optional>
#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php {


//TODO sync for ZTS, need to be pure global for ZTS, doesnt need to be global for NTS
template <typename key_t, typename handler_t>
class InternalFunctionInstrumentationStorage {
public:

    static auto &getInstance() {
        static InternalFunctionInstrumentationStorage instance_;
        return instance_;
    }

    handler_t get(size_t functionKey) {
        auto data = storage_.find(functionKey);
        if (data != storage_.end()) {
            return data->second;
        }
        return nullptr;
    }

    void store(key_t functionKey, handler_t originalHandler) {
        auto instrumentation = storage_.find(functionKey);
        if (instrumentation == std::end(storage_)) {
            storage_.emplace(functionKey, originalHandler);
        }
    }

    void remove(key_t functionKey) {
        storage_.erase(functionKey);
    }

private:
    std::unordered_map<key_t, handler_t> storage_;
};

}