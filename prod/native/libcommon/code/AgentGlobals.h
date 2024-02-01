#pragma once

#include "PhpBridgeInterface.h"
#include "PhpSapi.h"
#include "SharedMemoryState.h"
#include <memory>

namespace elasticapm::php {

class AgentGlobals {
public:
    AgentGlobals(std::shared_ptr<PhpBridgeInterface> bridge, PhpSapi sapi, std::shared_ptr<SharedMemoryState> sharedMemory) :
        bridge_(std::move(bridge)),
        sapi_(std::move(sapi)),
        sharedMemory_(std::move(sharedMemory)) {
    }

    std::shared_ptr<PhpBridgeInterface> bridge_;
    PhpSapi sapi_;
    std::shared_ptr<SharedMemoryState> sharedMemory_;
};

    
}