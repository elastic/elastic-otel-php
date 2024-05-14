#include "Logger.h"

#include <sys/syscall.h>
#include <sys/types.h>
#include <fcntl.h>
#include <unistd.h>
#include <syslog.h>

namespace elasticapm::php {

//TODO Windbg sink
// #ifdef PHP_WIN32
// void writeToWindowsSystemDebugger(const char *msg) {
//     ELASTIC_APM_ASSERT_VALID_STRING(msg);

//     OutputDebugStringA(msg);
// }
// #endif


bool Logger::doesMeetsLevelCondition(LogLevel level) const {
    auto maxLevel = LogLevel::logLevel_off;
    for (auto const &sink : sinks_) {
        maxLevel = std::max(sink->getLevel(), maxLevel);
    }
    return level <= maxLevel;
}

void Logger::attachSink(std::shared_ptr<LoggerSinkInterface> sink) {
    std::lock_guard<SpinLock> lock(spinLock_);
    sinks_.emplace_back(std::move(sink));
}

void Logger::printf(LogLevel level, const char *format, ...) const {
    va_list args;
    va_start(args, format);
    auto msg = elasticapm::utils::stringVPrintf(format, args);
    va_end(args);


    std::string output;
    output.append(getFormattedTime());
    output.append(" ");

    size_t indexOfProcessData = output.length();
    output.append(getFormattedProcessData());

    size_t indexOfLogLevel = output.length();
    output.append(" [");
    output.append(getLogLevelName(level));
    output.append("] ");

    size_t indexOfMessage = output.length();
    output.append(std::move(msg));

    output.push_back('\n');

    auto outputSv = std::string_view{output};

    auto msgSv = outputSv.substr(indexOfMessage, output.length() - indexOfMessage - 1);
    auto timeSv = outputSv.substr(0, indexOfProcessData - 1);
    auto processSv = outputSv.substr(indexOfProcessData, indexOfLogLevel - indexOfProcessData);
    auto levelSv = outputSv.substr(indexOfLogLevel + 1, indexOfMessage - indexOfLogLevel - 2);

    std::lock_guard<SpinLock> lock(spinLock_);
    for (auto const &sink : sinks_) {
        if (sink->getLevel() < level) {
            continue;
        }
        sink->writeLog(output, msgSv, timeSv, levelSv, processSv);
    }
}



std::string Logger::getFormattedTime() const {
    const auto now = std::chrono::system_clock::now();
    const std::time_t nowTime = std::chrono::system_clock::to_time_t(now);

    auto seconds = std::chrono::duration_cast<std::chrono::seconds>(now.time_since_epoch());
    int64_t mircros = std::chrono::duration_cast<std::chrono::microseconds>(now.time_since_epoch() - seconds).count();
    struct tm buf;
    gmtime_r(&nowTime, &buf);
    return elasticapm::utils::stringPrintf("[%4.4d-%2.2d-%2.2d %2.2d:%2.2d:%2.2d.%6.6d UTC]", buf.tm_year + 1900, buf.tm_mon + 1, buf.tm_mday, buf.tm_hour, buf.tm_min, buf.tm_sec, mircros);
}

std::string Logger::getFormattedProcessData() const {
    std::string data;
    data.append("[");
    data.append(std::to_string(getpid()));
    data.append("/");
    auto tid = syscall( SYS_gettid );
    data.append(std::to_string(tid));

    data.append("]");
    return data;
}


LogLevel LoggerSinkStdErr::getLevel() const {
    return level_;
}

void LoggerSinkStdErr::setLevel(LogLevel level) {
    level_ = level;
}


void LoggerSinkStdErr::writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const {
    // mt-safe (multithread)
    [[maybe_unused]] auto rv = ::write(STDERR_FILENO, formattedOutput.c_str(), formattedOutput.length());
}

LogLevel LoggerSinkSysLog::getLevel() const {
    return level_;
}

void LoggerSinkSysLog::setLevel(LogLevel level) {
    level_ = level;
}


void LoggerSinkSysLog::writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const {
    // mt-safe (multithread)
    ::syslog(LOG_ALERT, PRsv " " PRsv, PRsvArg(process), PRsvArg(message));
}

LogLevel LoggerSinkFile::getLevel() const {
    return level_;
}

void LoggerSinkFile::setLevel(LogLevel level) {
    level_ = level;
}


void LoggerSinkFile::writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const {
    if (fd_ < 0) {
        return;
    }
    // mt-safe (multithread)
    [[maybe_unused]] auto rv = ::write(fd_, formattedOutput.c_str(), formattedOutput.length());
}

//TODO note, in case of fork each worker can have different fd (and different log name) opened. Consided shm sync.
bool LoggerSinkFile::reopen(std::string fileName) {
    std::lock_guard<SpinLock> lock(spinLock_);

    if (openedFilePath_ == fileName) {
        return true;
    }

    if (fd_ >= 0) {
        close(fd_);
        openedFilePath_.clear();
    }

    fd_ = open(fileName.c_str(), O_APPEND | O_CREAT | O_WRONLY, S_IRWXU | S_IRWXG);
    if (fd_ >=0) {
        openedFilePath_ = std::move(fileName);
        return true;
    }
    return false;
}

}