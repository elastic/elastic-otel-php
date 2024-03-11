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