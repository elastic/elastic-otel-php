# Local development

## Repository structure

EDOT PHP is built on top of `opentelemetry-php-distro` which is included as a git submodule. All Elastic-specific customizations live outside the submodule:

| Directory / file | Description |
|-----------|-------------|
| `upstream/` | Git submodule - [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro). Contains the contrib build system, native extension, PHP code, tests, and tools. |
| `elastic_prod/` | Elastic-only production code: native C++ vendor layer (`libelastic/`), PHP bootstrap (`bootstrap_elastic.php`), vendor customizations, and OpAMP remote config. |
| `elastic_tests/` | Test patch system - `*.patch` files applied to contrib tests before component test runs, with `apply.sh` / `revert.sh` scripts. |
| `tools/` | EDOT thin wrappers that delegate to `upstream/tools/` (for example, `build_native.sh`, `test_phpt.sh`), plus Elastic-specific scripts (`configure_php_templates.sh`, `test_sources_license.sh`). |
| `packaging/` | Package definitions (`nfpm.yaml`) and install/uninstall scripts for deb/rpm/apk. |
| `docs/` | Elastic user-facing documentation (configuration, setup, migration, release notes). |
| `elastic-otel-php.properties` | EDOT's own properties file - holds only the EDOT package version. All other build parameters (supported PHP versions, `otel_proto_version`, etc.) are read from `upstream/project.properties` instead of being duplicated here. |

Most build and test scripts in `tools/` are thin wrappers that `cd upstream` and call the contrib equivalent, passing through all arguments. Elastic-specific scripts (license checks, template generation) run directly from the repo root.

This document only covers what's specific to EDOT. For everything else - building/testing the native library, managing PHP dependencies, Docker build images, Conan artifacts, releases - see [upstream/DEVELOPMENT.md](upstream/DEVELOPMENT.md).

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

`tools/build/build_native.sh` is EDOT-specific (not a thin wrapper): it runs its own Docker build so it can inject the Elastic vendor library (`libelastic/`) into the contrib CMake build via `-DCMAKE_PROJECT_INCLUDE`. Flags match upstream's script (see [upstream/DEVELOPMENT.md](upstream/DEVELOPMENT.md#building-the-native-library-like-on-ci) for what each one does):

```bash
cd elastic-otel-php
./tools/build/build_native.sh --build_architecture linux-x86-64 --interactive --ncpu 2
```

Supported architectures: `linux-x86-64`, `linuxmusl-x86-64`, `linux-arm64`, `linuxmusl-arm64`.

To enable debug logging in tested classes, export `ELASTIC_OTEL_DEBUG_LOG_TESTS=1` before running (EDOT's env var name - different from upstream's `OTEL_PHP_DEBUG_LOG_TESTS`).

### Building the native library for other platforms

Same manual Conan/CMake flow as upstream (see [upstream/DEVELOPMENT.md](upstream/DEVELOPMENT.md#building-the-native-library-for-other-platforms) for prerequisites and details), with one addition - pass `-DCMAKE_PROJECT_INCLUDE` to inject the Elastic vendor library:

```bash
./upstream/prod/native/building/install_dependencies.sh --build_output_path ./upstream/prod/native/_build/custom-release --build_type Release --detect_conan_profile

cmake -S ./upstream/prod/native/ -B ./upstream/prod/native/_build/custom-release/ \
  -DCMAKE_PREFIX_PATH=./upstream/prod/native/_build/custom-release/build/Release/generators/ \
  -DCMAKE_PROJECT_INCLUDE=${PWD}/elastic_prod/native/libelastic/elastic_vendor_inject.cmake \
  -DSKIP_CONAN_INSTALL=1 -DCMAKE_BUILD_TYPE=Release

cmake --build ./upstream/prod/native/_build/custom-release/
```

Built libraries land under `upstream/prod/native/_build/custom-release/{loader,extension}/code/`.

### Testing the native library

`tools/build/test_phpt.sh` is a thin wrapper for upstream's script of the same name (see [upstream/DEVELOPMENT.md](upstream/DEVELOPMENT.md#testing-the-native-library)). Run it from the EDOT repo root against the library built in the previous step:

```bash
cd elastic-otel-php
./tools/build/test_phpt.sh --build_architecture linux-x86-64 --php_versions '81 82 83 84'
```

### Building PHP code for the packages

`tools/build/build_php_code_for_packages.sh` delegates to upstream's script of the same name - composer install, php-scoper prefixing, NOTICE generation (see [upstream/DEVELOPMENT.md](upstream/DEVELOPMENT.md#building-php-dependencies)) - then additionally configures EDOT-specific PHP templates (e.g. `ElasticVendorCustomizations.php`) and appends the upstream-generated notices to EDOT's own `_BUILT/NOTICE`.

```bash
cd elastic-otel-php
./tools/build/build_php_code_for_packages.sh --php_versions '81 82 83 84'
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

PHP dependencies (`composer.json`, `composer.lock`, `vendor/`) are fully managed in the contrib submodule. See the [upstream DEVELOPMENT.md](upstream/DEVELOPMENT.md#managing-php-3rd-party-dependencies) for instructions on installing, checking, and updating PHP dependencies.

`tools/build/build_php_code_for_packages.sh` delegates to the equivalent upstream script (see the ["Building PHP code for the packages"](#building-php-code-for-the-packages) section above for what it additionally does), so you don't need to `cd upstream` first. All changes to `composer.json`, `composer.lock`, and the `vendor` directory should still be made - and committed - inside the `upstream/` submodule, then contributed back to the contrib [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro) repository.

# Documentation

The official documentation is available at:
[https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html](https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html)

It is automatically generated from source files located in the main repository:
[https://github.com/elastic/opentelemetry](https://github.com/elastic/opentelemetry)

The EDOT PHP documentation specifically resides in the following subdirectory:
[https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php](https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php)

If your changes require updates to the documentation, please make sure to update the relevant files in the documentation source directory accordingly.
