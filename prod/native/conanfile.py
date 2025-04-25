from conan import ConanFile
from conan.tools.cmake import cmake_layout

class MyProjectConan(ConanFile):
    name = "my_project"
    version = "0.1"
    settings = ["os", "arch", "compiler", "build_type"]
    generators = "CMakeDeps"
    layout = cmake_layout

    requires = [
        "libcurl/8.10.1",
        "libunwind/1.8.1",
        "magic_enum/0.9.6",
        "boost/1.86.0",
        "gtest/1.15.0",
        "protobuf-custom/5.27.0",
        "php-headers-81/2.0",
        "php-headers-82/2.0",
        "php-headers-83/2.0",
        "php-headers-84/2.0",
        "openssl/3.4.1",
    ]

    default_options = {
        "*:shared": False,
        "openssl/*:shared": True,
        "boost/*:header_only": False,
        "libcurl/*:shared": True,
        "libcurl/*:with_libssh2": True,
        "libprotobuf/*:shared": False,
        "libprotobuf/*:fPIC": True,
        "libprotobuf/*:debug_suffix": False,
        "libprotobuf/*:with_zlib": False,
        "libprotobuf/*:with_rtti": True,
        "libprotobuf/*:upb": False,
        "libabseil/*:shared": False,
        "libabseil/*:fPIC": True,
        "b2/*:use_cxx_env": True,
        "b2/*:toolset": "cxx",
    }

    # def requirements(self):
    #     self.requires("b2/5.3.1", options={"use_cxx_env": True, "toolset": "cxx"})

    def build_requirements(self):
        # See https://github.com/conan-io/conan-center-index/issues/27165
        self.tool_requires('b2/5.3.1', override=True)