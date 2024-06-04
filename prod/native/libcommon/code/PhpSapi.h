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

#include <string>


namespace elasticapm::php {


class PhpSapi {
public:
    enum class Type : uint8_t {
        Apache,
        FPM,
        CLI,
        CLI_SERVER,
        CGI,
        CGI_FCGI,
        LITESPEED,
        PHPDBG,
        EMBED,
        FUZZER,
        UWSGI,
        FRANKENPHP,
        UNKNOWN,
    };

    PhpSapi(std::string_view sapiName) : name_{sapiName}, type_{parseSapi(sapiName)} {
    }

    bool isSupported() const {
        return type_ != Type::UNKNOWN && type_ != Type::PHPDBG && type_ != Type::EMBED;
    }

    std::string_view getName() const {
        return name_;
    }

    Type getType() const {
        return type_;
    }

private:
    Type parseSapi(std::string_view sapiName) {
        if (sapiName == "cli") {
            return Type::CLI;
        } else if (sapiName == "cli-server") {
            return Type::CLI_SERVER;
        } else if (sapiName == "cgi") {
            return Type::CGI;
        } else if (sapiName == "cgi-fcgi") {
            return Type::CGI_FCGI;
        } else if (sapiName == "fpm-fcgi") {
            return Type::FPM;
        } else if (sapiName == "apache2handler") {
            return Type::Apache;
        } else if (sapiName == "litespeed") {
            return Type::LITESPEED;
        } else if (sapiName == "phpdbg") {
            return Type::PHPDBG;
        } else if (sapiName == "embed") {
            return Type::EMBED;
        } else if (sapiName == "fuzzer") {
            return Type::FUZZER;
        } else if (sapiName == "uwsgi") {
            return Type::UWSGI;
        } else if (sapiName == "frankenphp") {
            return Type::FRANKENPHP;
        } else {
            return Type::UNKNOWN;
        }
    }

    std::string name_;
    Type type_;
};

}


