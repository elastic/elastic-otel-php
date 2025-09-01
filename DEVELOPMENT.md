# Local development
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
./prod/native/building/install_dependencies.sh --build_output_path ./prod/native/_build/custom-release --build_type Release --detect_conan_profile
```

The script will install dependencies and generate the files necessary to configure the project in the next step (prod/native/_build/custom-release):

```bash
cmake -S ./prod/native/ -B ./prod/native/_build/custom-release/  -DCMAKE_PREFIX_PATH=./prod/native/_build/custom-release/build/Release/generators/ -DSKIP_CONAN_INSTALL=1 -DCMAKE_BUILD_TYPE=Release
```

Building:
```bash
cmake --build ./prod/native/_build/custom-release/
```

If the build is successful, you can find the built libraries using the following command:
```bash
find prod/native/_build/custom-release -name elastic*.so
```

As a result you should see:
```bash
prod/native/_build/custom-release/loader/code/elastic_otel_php_loader.so
prod/native/_build/custom-release/extension/code/elastic_otel_php_81.so
prod/native/_build/custom-release/extension/code/elastic_otel_php_82.so
prod/native/_build/custom-release/extension/code/elastic_otel_php_83.so
```



### Testing the native library

The following script will run the phpt tests for the native library, which should be built in the previous step - make sure to use the same architecture. You can run tests for multiple PHP versions simultaneously by providing several versions separated by a space to the `--php_versions` parameter.

```bash
cd elastic-otel-php
  ./tools/build/test_phpt.sh --build_architecture linux-x86-64 --php_versions '81 82 83'
```

### Building PHP dependencies

To ensure the instrumentation is fully successful, it is required to download and install dependencies for the PHP implementation. You can do this automatically using a script that will download and install them separately for each specified PHP version. Similar to the previous step, you need to provide the PHP versions separated by spaces as a parameter to the `--php_versions` argument.

```bash
cd elastic-otel-php
  ./tools/build/build_php_deps.sh --php_versions '81 82 83'
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
./tools/license/insert_license.py prod/native cpp h
```


# Updating docker images used for building and testing
## Building and updating docker images used to build the agent extension

If you want to update images used to build native extension, you need to go into `prod/native/building/dockerized` folder and modify Dockerfile stored in images folder. In this moment, there are four Dockerfiles:

`Dockerfile_musl` for Linux x86_64 with musl libc implementation\
`Dockerfile_glibc` for all other x86_64 distros with glibc implementation\
`Dockerfile_arm64` for all ARM64 linux distros with glibc implementation\
`Dockerfile_arm64_musl` for ARM64 Linux with musl libc implementation

Then you need to increment image version in `docker-compose.yml`. Remember to update Dockerfiles for all architectures, if needed. To build new images, you just need to call:
```bash
docker compose build
```
It will build images for all supported architectures. As a result you should get summary like this:
```bash
Successfully tagged elasticobservability/apm-agent-php-dev:native-build-gcc-14.2.0-linux-x86-64-0.0.1
Successfully tagged elasticobservability/apm-agent-php-dev:native-build-gcc-14.2.0-linuxmusl-x86-64-0.0.1
```

Be aware that if you want to build images for ARM64 you must run it on ARM64 hardware or inside emulator. The same applies to x86-64.

To test freshly built images, you need to udate image version in ```./tools/build/build_native.sh``` script and run build task described in [Build/package](#build-and-package)

\
If everything works as you expected, you just need to push new image to dockerhub by calling:
```bash
docker push elasticobservability/apm-agent-php-dev:native-build-gcc-14.2.0-linux-x86-64-0.0.1
```

## Adding or removing support for PHP release

- Add the new version to the `supported_php_versions` list in the [elastic-otel.properties](elastic-otel.properties) file.
- Update supported PHP version detection in function `is_php_supported` in [post-install.sh](packaging/scripts/post-install.sh)
- Add or modify the supported versions array in the loader's [phpdetection.cpp](prod/native/loader/code/phpdetection.cpp) file.
- Add or remove metadata for the specified PHP version in [conandata.yml](prod/native/building/dependencies/php-headers/conandata.yml).
- Add or remove the Conan dependency for php-headers-* in [conanfile.txt](prod/native/conanfile.txt).
- Follow the steps in the ["Building the native library like on CI"](#building-the-native-library-like-on-ci) section to configure and build the agent.
- To speed up CI builds, upload Conan artifacts to Artifactory if support for the new PHP release has been added (see [Building and publishing conan artifacts](#building-and-publishing-conan-artifacts))


## Building and publishing conan artifacts

First, please remember that you need to perform all steps inside a proper docker container. This will ensure that each package receives the same unique identifier (and package will be used in CI build).

The following are instructions for building and uploading artifacts for the linux-x86-64 architecture

Execution of container. All you need to do here is to use latest container image revision and replace path to your local repository.
```bash
docker run -ti -v /your/forked/repository/path/elastic-otel-php:/source -w /source/agent/native elasticobservability/apm-agent-php-dev:native-build-gcc-14.2.0-linux-x86-64-0.0.1 bash
```

In container environment we need to configure project - it will setup build environment, conan environment and build all required conan dependencies
```bash
cmake --preset linux-x86-64-release
```

Now we need to load python virtual environment created in previous step. This will enable path to conan tool.
```bash
source _build/linux-x86-64-release/venv/bin/activate
```

You can list all local conan packages simply by calling:
```bash
conan list -c "*"
```

it should output listing similar to this:
```bash
Local Cache
...
  php-headers-81
    php-headers-81/2.0
  php-headers-82
    php-headers-82/2.0
  php-headers-83
    php-headers-83/2.0
...
```

Now you need to login into conan as elastic user. Package upload is allowed only for mainteiners. You need to generate token from UI and use is instead of password.
```bash
conan remote login ElasticConan user@elastic.co
```

Now you can upload package to conan artifactory.

```bash
conan upload -r=ElasticConan php-headers-81
```

Now you can check conan artifactory for new packages here:
https://artifactory.elastic.dev/ui/repos/tree/General/apm-agent-php-dev

and in "raw" format here:
https://artifactory.elastic.dev/ui/native/apm-agent-php-dev/

# Managing PHP 3rd party dependencies
This documentation section describes how to manage PHP 3rd party dependencies
i.e., `vendor` directory, `composer.json` and `composer.lock`

We would like to have reproducible builds, so we need to ensure that the same
versions of dependencies are used for each build of the source code repository snapshot.
To achieve this, we committed `composer.lock` files to version control.
There are multiple `composer.lock` files - one for each supported major.minor PHP version. 

## To install dependencies

Run 
```
composer run-script -- install-using-generated-lock-dev
```
Instead of the usual `composer install`.
This will copy composer's lock file for the current PHP version to `composer.lock`
and run `composer install`
(with `ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND` environment variable set to `true` to avoid infinite loop).

## To check which dependencies can be updated
Run
```
composer outdated
```

## To update dependencies
1) Update `composer.json` to the desired version of the dependency
2) Run
```
./tools/build/generate_composer_lock_files.sh && composer run-script -- install-using-generated-lock-dev
```
instead of the usual `composer update`
3) Commit the changes to the composer's lock files

# Documentation

The official documentation is available at:
[https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html](https://www.elastic.co/docs/reference/opentelemetry/edot-sdks/php/index.html)

It is automatically generated from source files located in the main repository:
[https://github.com/elastic/opentelemetry](https://github.com/elastic/opentelemetry)

The EDOT PHP documentation specifically resides in the following subdirectory:
[https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php](https://github.com/elastic/opentelemetry/tree/main/docs/_edot-sdks/php)

If your changes require updates to the documentation, please make sure to update the relevant files in the documentation source directory accordingly.
