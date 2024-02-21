#pragma once

#include "PhpBridgeInterface.h"
#include "PhpSapi.h"
#include "SharedMemoryState.h"
#include "PeriodicTaskExecutor.h"
#include <memory>

namespace elasticapm::php {

class AgentGlobals {
public:
    AgentGlobals(std::shared_ptr<PhpBridgeInterface> bridge, PhpSapi sapi, std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor, std::shared_ptr<SharedMemoryState> sharedMemory) :
        bridge_(std::move(bridge)),
        sapi_(std::move(sapi)),
        periodicTaskExecutor_(std::move(periodicTaskExecutor)),
        sharedMemory_(std::move(sharedMemory)) {
    }

    std::shared_ptr<PhpBridgeInterface> bridge_;
    PhpSapi sapi_;
    std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor_;
    std::shared_ptr<SharedMemoryState> sharedMemory_;
};

    
}
