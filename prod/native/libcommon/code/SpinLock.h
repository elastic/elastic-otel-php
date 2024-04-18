#pragma once

#include <atomic>

namespace elasticapm::php {

class SpinLock {
public:
    void lock() {
        while (flag.test_and_set(std::memory_order_acquire)) {
#if defined(__cpp_lib_atomic_flag_test) // https://en.cppreference.com/w/cpp/atomic/atomic_flag
            while (flag.test(std::memory_order_relaxed))
                ; // test lock
#endif
        }
    }

    void unlock() {
        flag.clear(std::memory_order_release);
    }

private:
    std::atomic_flag flag = ATOMIC_FLAG_INIT;
};

} // namespace elasticapm::php
