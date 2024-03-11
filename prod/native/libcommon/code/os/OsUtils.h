#pragma once

#include <string>

namespace elasticapm::osutils {

std::string getCommandLine();
std::string getProcessEnvironment();

pid_t getCurrentProcessId();
pid_t getCurrentThreadId();
pid_t getParentProcessId();

}
