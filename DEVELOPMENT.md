# Local development

## Repository structure

EDOT PHP is built on top of `opentelemetry-php-distro` which is included as a git submodule. All Elastic-specific customizations live outside the submodule:

| Directory | Description |
|-----------|-------------|
| `upstream/` | Git submodule — [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro). Contains the contrib build system, native extension, PHP code, tests, and tools. |
| `elastic_prod/` | Elastic-only production code: native C++ vendor layer (`libelastic/`), PHP bootstrap (`bootstrap_elastic.php`), vendor customizations, and OpAMP remote config. |
| `elastic_tests/` | Test patch system — `*.patch` files applied to contrib tests before component test runs, with `apply.sh` / `revert.sh` scripts. |
| `tools/` | EDOT thin wrappers that delegate to `upstream/tools/` (for example, `build_native.sh`, `test_phpt.sh`), plus Elastic-specific scripts (`configure_php_templates.sh`, `test_sources_license.sh`). |
| `packaging/` | Package definitions (`nfpm.yaml`) and install/uninstall scripts for deb/rpm/apk. |
| `docs/` | Elastic user-facing documentation (configuration, setup, migration, release notes). |

Most build and test scripts in `tools/` are thin wrappers that `cd upstream` and call the contrib equivalent, passing through all arguments. Elastic-specific scripts (license checks, template generation) run directly from the repo root.

### Contributing changes

Changes that are **not Elastic-specific** (bug fixes, new instrumentations, performance improvements, etc.) should be contributed directly to the contrib [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro) repository. Once merged upstream, update the `upstream/` submodule in this repo to pull them in:

```bash
cd upstream
git fetch origin
git checkout <new-commit-or-tag>
cd ..
git add upstream
git commit -m "Update upstream submodule to <version>"
```

Only Elastic-specific code (vendor identity, OpAMP config, bootstrap, packaging, CI wrappers) should be added or modified in this repository.

## Build and package

The best method for building is to use a set of Bash scripts that we utilize in production workflows for building releases.

All scripts are located in the `tools/build` folder, but they should be called from the root folder of the repository. To ensure everything works correctly on your system, you need to have Docker installed.
Each of the scripts mentioned below has a help page; to display it, simply provide the `--help` argument.

### Building the native library like on CI

```bash
cd elastic-otel-php
./tools/build/build_native.sh --build_architecture linux-x86-64 --interactive --ncpu 2
```

This script will configure the project and build the libraries for the linux-x86-64 architecture. Adding the interactive argument allows you to interrupt the build using the `Ctrl + C` combination, and with the ncpu option, you can build in parallel using the specified number of processor threads.
If you are not adding new files to the project and just want to rebuild your changes,
you can provide the `--skip_configure` argument - this will save time on reconfiguring the project.
You can also save a lot of time by creating a local cache for Conan packages;
the files will then be stored outside the container and reused repeatedly.
To do this, provide a path to the `--conan_cache_path` argument, e.g., `~/.conan_cache`.
The script will automatically execute native unit tests just after the build.
If you would like to skip native unit tests you can use `--skip_unit_tests` command line option.

Currently, we support the following architectures:

```bash
linux-x86-64
linuxmusl-x86-64
linux-arm64
linuxmusl-arm64
```

If you want to enable debug logging in tested classes, you need to export environment variable `ELASTIC_OTEL_DEBUG_LOG_TESTS=1` before run.

### Building the native library for other platforms

You can always try to compile the native part for an unsupported architecture or platform. To facilitate this, we have made it possible to remove hard dependencies on Docker images, the compiler, and build profiles.

To make everything work on your system, you will need the gcc compiler (at the time of writing, version 12.0+), cmake (v3.26+), and python 3.x.

Since our system uses Conan as the repository for required dependencies, you need to install them first. The following script will install everything necessary in the `~/.conan2` folder. If you haven't used Conan before, provide the argument `--detect_conan_profile` to create a default profile – if you have used Conan before, you can skip this. If you are not using python-venv and have Conan installed directly on your system, you can pass the argument `--skip_venv_conan`, which will cause the script to skip creating a venv and installing Conan.

```bash
./upstream/prod/native/building/install_dependencies.sh --build_output_path ./upstream/prod/native/_build/custom-release --build_type Release --detect_conan_profile
```

The script will install dependencies and generate the files necessary to configure the project in the next step. Note the `-DCMAKE_PROJECT_INCLUDE` argument — it injects the Elastic vendor library (`libelastic`) into the contrib build:

```bash
cmake -S ./upstream/prod/native/ -B ./upstream/prod/native/_build/custom-release/ \
  -DCMAKE_PREFIX_PATH=./upstream/prod/native/_build/custom-release/build/Release/generators/ \
  -DCMAKE_PROJECT_INCLUDE=${PWD}/elastic_prod/native/libelastic/elastic_vendor_inject.cmake \
  -DSKIP_CONAN_INSTALL=1 -DCMAKE_BUILD_TYPE=Release
```

Building:
```bash
cmake --build ./upstream/prod/native/_build/custom-release/
```

If the build is successful, you can find the built libraries using the following command:
```bash
find upstream/prod/native/_build/custom-release -name opentelemetry*.so
```

As a result you should see:
```bash
upstream/prod/native/_build/custom-release/loader/code/opentelemetry_php_distro_loader.so
upstream/prod/native/_build/custom-release/extension/code/opentelemetry_php_distro_84.so
upstream/prod/native/_build/custom-release/extension/code/opentelemetry_php_distro_83.so
upstream/prod/native/_build/custom-release/extension/code/opentelemetry_php_distro_82.so
upstream/prod/native/_build/custom-release/extension/code/opentelemetry_php_distro_81.so
```

### Testing the native library

The following script will run the phpt tests for the native library, which should be built in the previous step - make sure to use the same architecture. You can run tests for multiple PHP versions simultaneously by providing several versions separated by a space to the `--php_versions` parameter.

```bash
cd elastic-otel-php
  ./tools/build/test_phpt.sh --build_architecture linux-x86-64 --php_versions '81 82 83 84'
```

### Building PHP dependencies

To ensure the instrumentation is fully successful, it is required to download and install dependencies for the PHP implementation. You can do this automatically using a script that will download and install them separately for each specified PHP version. Similar to the previous step, you need to provide the PHP versions separated by spaces as a parameter to the `--php_versions` argument.

```bash
cd elastic-otel-php
  ./tools/build/build_php_deps.sh --php_versions '81 82 83 84'
```

### Building Packages

We currently support building packages for Debian-based systems (deb), Red Hat-based systems (rpm), and Alpine Package Keeper (apk) for each supported CPU architecture.

To build a package, use the `./tools/build/build_packages.sh` script with the following arguments:
```
  --package_version        Required. Version of the package.
  --build_architecture     Required. Architecture of the native build. (eg. linux-x86-64)
  --package_goarchitecture Required. Architecture of the package in Golang convention. (eg. amd64)
  --package_sha            Optional. SHA of the package. Default is fetch from git commit hash or unknown if got doesn't exists.
  --package_types          Required. List of package types separated by spaces (e.g., 'deb rpm').
```

For the `--package_goarchitecture` parameter, we currently distinguish between two architectures: amd64 and arm64. These should correspond to the value of the `--build_architecture` argument.

Remember, it's best if the package version reflects the version recorded in the `elastic-otel-php.properties` file.

```bash
cd elastic-otel-php
./tools/build/build_packages.sh --package_version v1.0.0-dev --build_architecture linux-x86-64 --package_goarchitecture amd64 --package_types 'deb rpm'
```


### License Check

If you intend to contribute, all source files must include the appropriate license header. Before pushing your changes, it is advisable to verify the correctness of the licenses using a script.

```bash
cd elastic-otel-php
./tools/build/test_sources_license.sh
```

### License Update

If the license check fails, you can use a script that automatically updates and adds license headers to the appropriate files. The script requires Python version 3 to run. The first argument should be the path to the folder where the script will recursively check and update files with the extensions provided in the subsequent arguments.

```bash
cd elastic-otel-php
./tools/license/insert_license.py elastic_prod/native cpp h
```

# Updating docker images used for building and testing

Docker images for building and testing are managed in the contrib repository. See the [upstream DEVELOPMENT.md](upstream/DEVELOPMENT.md) for instructions on building, updating, and publishing docker images.

Changes to docker images should be contributed to the contrib [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro) repository.

## Building and publishing conan artifacts

Since we use contrib as a base, Conan artifacts are cached in the contrib docker images. Any additional artifacts should be managed in the contrib repository. See the [upstream DEVELOPMENT.md](upstream/DEVELOPMENT.md) for details on building and publishing Conan artifacts.

# Managing PHP 3rd party dependencies

PHP dependencies are managed in the contrib submodule. See the [upstream DEVELOPMENT.md](upstream/DEVELOPMENT.md) for instructions on installing, checking, and updating PHP dependencies.

All changes to `composer.json`, `composer.lock`, and the `vendor` directory should be made in the contrib [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro) repository.

# Documentation

The official documentation is available at:
[https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html](https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html)

It is automatically generated from source files located in the main repository:
[https://github.com/elastic/opentelemetry](https://github.com/elastic/opentelemetry)

The EDOT PHP documentation specifically resides in the following subdirectory:
[https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php](https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php)

If your changes require updates to the documentation, please make sure to update the relevant files in the documentation source directory accordingly.
