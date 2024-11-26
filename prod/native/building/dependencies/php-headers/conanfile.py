import os
import re
from conan import ConanFile
from conan.tools.build import cross_building
from conan.tools.cmake import CMake, CMakeToolchain, CMakeDeps, cmake_layout
from conan.tools.env import Environment
from conan.tools.files import get, copy, chdir
from conan.tools.gnu import AutotoolsToolchain, Autotools
from conan.tools.layout import basic_layout

class PhpHeadersForPHPConan(ConanFile):
    description = "PHP headers package required to build native part of Elastic OTel distro without additional PHP dependencies"
    license = "The PHP License, version 3.01"
    homepage = "https://php.net/"
    url = "https://php.net/"
    author = "pawel.filipczak@elastic.co"
    settings = "os", "compiler", "build_type", "arch"
    platform = "linux"
    options = {
        "header_only": [True],
        "with_xml_sqlite": [True, False],
    }
    default_options = {
        "header_only": True,
        "with_xml_sqlite": False,
    }

    def layout(self):
        cmake_layout(self, src_folder="src")

    def requirements(self):
        if self.options.with_xml_sqlite:
            self.requires("libxml2/2.9.9")
            self.requires("sqlite3/3.46.1")

    def build_requirements(self):
        self.tool_requires("bison/3.8.2")

    def init(self):
        self.source_temp_dir = "php-src"

    def getPHPVersions(self):
        num = re.findall(r'\d+', self.name)
        self.php_major_version = num[0]
        self.php_source_info = self.conan_data["sources"][self.php_major_version][self.platform]
        self.output.info(f"PHP source code version: {self.php_source_info['version']}")
        self.php_source_info['url'] = self.php_source_info['url'].format(self.php_source_info['version'])
        self.output.info(f"PHP source code URL: {self.php_source_info['url']}")
        self.php_source_info['contentsRoot'] = self.php_source_info['contentsRoot'].format(self.php_source_info['version'])

    def set_version(self):
        self.version = self.conan_data["version"]

    def source(self):
        self.getPHPVersions()
        get(self, self.php_source_info['url'])
        os.rename(self.php_source_info['contentsRoot'], self.source_temp_dir)

    def generate(self):
        tc = AutotoolsToolchain(self)
        tc.generate()

    def build(self):
        env = Environment()
        args = []
        if self.options.with_xml_sqlite:
            env.define('ac_cv_php_xml2_config_path', os.path.join(self.dependencies["libxml2"].package_folder, "bin/xml2-config"))
            env.define('LIBXML_LIBS', os.path.join(self.dependencies["libxml2"].package_folder, self.dependencies["libxml2"].cpp_info.libdirs[0]))
            env.define('LIBXML_CFLAGS', f"-I{os.path.join(self.dependencies['libxml2'].package_folder, self.dependencies['libxml2'].cpp_info.includedirs[0])}")
            env.define('SQLITE_LIBS', os.path.join(self.dependencies["sqlite3"].package_folder, self.dependencies["sqlite3"].cpp_info.libdirs[0]))
            env.define('SQLITE_CFLAGS', f"-I{os.path.join(self.dependencies['sqlite3'].package_folder, self.dependencies['sqlite3'].cpp_info.includedirs[0])}")
        else:
            args = ["--disable-xml", "--without-libxml", "--without-sqlite3", "--disable-dom", "--without-pdo-sqlite", "--disable-simplexml", "--disable-xmlreader", "--disable-xmlwriter"]

        # Build the source
        with chdir(self, os.path.join(self.source_folder, self.source_temp_dir)):
            self.run("./buildconf --force")

        with env.vars(self).apply():
            autotools = Autotools(self)
            autotools.configure(build_script_folder = self.source_temp_dir, args=args)

        if not self.options.header_only:
            autotools.make()

    def package(self):
        # Copy header files to the package folder
        source_dir = os.path.join(self.source_folder, self.source_temp_dir)
        copy(self, "*.h", src=self.build_folder, dst=os.path.join(self.package_folder, "include"))
        copy(self, "*.h", src=source_dir, dst=os.path.join(self.package_folder, "include"))

    def package_id(self):
        # Ignore compiler version for package identity
        del self.info.settings.compiler.version

    def package_info(self):
        self.cpp_info.set_property("cmake_file_name", self.name)
        self.cpp_info.set_property("cmake_target_name", f"{self.name}::{self.name}")
        self.cpp_info.set_property("pkg_config_name",  self.name)
