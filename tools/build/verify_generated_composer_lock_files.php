<?php

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap_build_tools.php';

InstallPhpDeps::verifyGeneratedComposerLockFiles();
