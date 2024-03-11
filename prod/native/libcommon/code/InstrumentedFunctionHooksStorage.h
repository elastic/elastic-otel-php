
#pragma once

#include <exception>
#include <list>
#include <unordered_map>

namespace elasticapm::php {

class InstrumentedFunctionHooksStorageInterface {
public:
    virtual ~InstrumentedFunctionHooksStorageInterface() = default;
    virtual void clear() = 0;
};


template<typename key_t, typename callback_t>
class InstrumentedFunctionHooksStorage : public InstrumentedFunctionHooksStorageInterface {
public:
    using callbacks_t = std::pair<callback_t, callback_t>;

    void store(key_t functionKey, callback_t callableOnEntry, callback_t callableOnExit) {
        callbacks_[functionKey].emplace_back(callbacks_t(std::move(callableOnEntry), std::move(callableOnExit)));
    }

    std::list<callbacks_t> &find(key_t functionKey) {
        auto found = callbacks_.find(functionKey);
        if (found == std::end(callbacks_)) {
            throw std::runtime_error("Callback not found");
        }
        return found->second;
    }

    void clear() final {
        callbacks_.clear();
    }

private:
    std::unordered_map<key_t, std::list<callbacks_t>> callbacks_;
};


}