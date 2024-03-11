#pragma once


namespace elasticapm::php {

enum InternalChecksLevel {
    internalChecksLevel_not_set = -1,
    internalChecksLevel_off = 0,

    internalChecksLevel_1,
    internalChecksLevel_2,
    internalChecksLevel_3,

    internalChecksLevel_all,
    numberOfInternalChecksLevels = internalChecksLevel_all + 1
};

}