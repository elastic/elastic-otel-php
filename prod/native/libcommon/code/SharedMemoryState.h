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

#pragma once

#include <boost/interprocess/anonymous_shared_memory.hpp>
#include <boost/interprocess/mapped_region.hpp>
#include <boost/interprocess/sync/interprocess_upgradable_mutex.hpp>
#include <boost/interprocess/sync/scoped_lock.hpp>
#include <boost/interprocess/sync/sharable_lock.hpp>

namespace elasticapm::php {

class SharedMemoryState {
public:
    struct SharedData {
        boost::interprocess::interprocess_upgradable_mutex mutex;
        bool oneTimeTaskAmongWorkersExecuted = false;
    };

    bool shouldExecuteOneTimeTaskAmongWorkers() {
        {
            boost::interprocess::sharable_lock< decltype( SharedData::mutex ) > lock( data_->mutex );
            if ( data_->oneTimeTaskAmongWorkersExecuted )
            {
                return false;
            }
        }

        boost::interprocess::scoped_lock< decltype( SharedData::mutex ) > ulock( data_->mutex );
        if ( data_->oneTimeTaskAmongWorkersExecuted )
        {
            return false;
        }
        data_->oneTimeTaskAmongWorkersExecuted = true;
        return true;
    }


protected:
    boost::interprocess::mapped_region region_{ boost::interprocess::anonymous_shared_memory( sizeof( SharedData ) ) };
    SharedData* data_{ new (region_.get_address()) SharedData };
};

}