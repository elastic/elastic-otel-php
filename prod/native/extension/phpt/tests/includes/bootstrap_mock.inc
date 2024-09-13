<?php

declare(strict_types=1);

namespace Elastic\OTel;

final class PhpPartFacade
{
    public static function bootstrap(string $elasticOTelNativePartVersion, int $maxEnabledLogLevel, float $requestInitStartTime): bool {
        return true;
    }

    public static function shutdown(): void {
    }

    public static function handleError(): void {
    }
}
