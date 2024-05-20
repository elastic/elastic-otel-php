#pragma once

#include <string>
#include <sys/types.h> 

namespace elasticapm::osutils {

std::string getCommandLine();
std::string getProcessEnvironment();

pid_t getCurrentProcessId();
pid_t getCurrentThreadId();
pid_t getParentProcessId();

}
