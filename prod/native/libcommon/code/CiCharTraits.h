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

namespace elasticapm::utils {

struct CiCharTraits : public std::char_traits<char> {
    static char to_upper(char c) {
        return std::toupper(static_cast<unsigned char>(c));
    }

    static bool eq(char c1, char c2) {
        return to_upper(c1) == to_upper(c2);
    }

    static bool lt(char c1, char c2) {
        return to_upper(c1) < to_upper(c2);
    }

    static int compare(const char *s1, const char *s2, std::size_t n) {
        while (n-- != 0) {
            if (to_upper(*s1) < to_upper(*s2))
                return -1;
            if (to_upper(*s1) > to_upper(*s2))
                return 1;
            ++s1;
            ++s2;
        }
        return 0;
    }

    static const char *find(const char *s, std::size_t n, char a) {
        const auto ua{to_upper(a)};
        while (n-- != 0) {
            if (to_upper(*s) == ua)
                return s;
            s++;
        }
        return nullptr;
    }
};

using istring_view = std::basic_string_view<char, CiCharTraits>;

template <class DstTraits, class CharT, class SrcTraits> constexpr std::basic_string_view<CharT, DstTraits> traits_cast(const std::basic_string_view<CharT, SrcTraits> src) noexcept {
    return {src.data(), src.size()};
}

inline namespace string_view_literals {
inline constexpr istring_view operator""_cisv(const char *__str, size_t __len) noexcept {
    return istring_view{__str, __len};
}
} // namespace string_view_literals

} // namespace elasticapm::utils