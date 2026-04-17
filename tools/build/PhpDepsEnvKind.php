<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

use OpenTelemetry\Distro\Util\EnumUtilTrait;

enum PhpDepsEnvKind
{
    use EnumUtilTrait;

    case dev;
    case prod;
    case prod_static_check;
    case test;
}
