#pragma once

#include <optional>
#include <string>
#include <string_view>
#include <unordered_map>
#include <vector>

namespace elasticapm::php {


//TODO sync for ZTS, need to be pure global for ZTS, doesnt need to be global for NTS
template <typename key_t, typename handler_t, typename callback_t>
class InternalFunctionInstrumentationStorage {
public:

    static auto &getInstance() {
        static InternalFunctionInstrumentationStorage instance_;
        return instance_;
    }

    struct FunctionData {
        std::vector<callback_t> callableOnEntry;
        std::vector<callback_t> callableOnExit;
        handler_t originalHandler = nullptr;
    };

    FunctionData *get(size_t functionKey) {
        auto data = storage_.find(functionKey);
        if (data != storage_.end()) {
            return &data->second;
        }
        return nullptr;
    }

    void store(key_t functionKey, callback_t callableOnEntry, callback_t callableOnExit, std::optional<handler_t> originalHandler) {
        auto instrumentation = storage_.find(functionKey);
        if (instrumentation == std::end(storage_)) {
            FunctionData data;
            data.callableOnEntry.emplace_back(std::move(callableOnEntry));
            data.callableOnExit.emplace_back(std::move(callableOnExit));
            data.originalHandler = originalHandler.value();


            storage_.emplace(functionKey, std::move(data));
        } else {
            FunctionData &data = instrumentation->second;
            data.callableOnEntry.emplace_back(std::move(callableOnEntry));
            data.callableOnExit.emplace_back(std::move(callableOnExit));
        }
    }

    void remove(key_t functionKey) {

    }

private:
    std::unordered_map<key_t, FunctionData> storage_;
};

}