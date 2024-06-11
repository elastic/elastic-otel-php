<?php

declare(strict_types=1);
namespace Elastic\OTel\AutoInstrument;

final class PhpPartFacade
{
    private static $singletonInstance = null;

    public static function bootstrap(int $maxEnabledLogLevel, float $requestInitStartTime): bool {
        self::$singletonInstance = new self();
        return true;
    }

    private static function singletonInstance(): self {
        if (self::$singletonInstance === null) {
            self::$singletonInstance = new self();
        }
        return self::$singletonInstance;
    }

    public static function ensureHaveLatestDataDeferredByExtension(): void {
    }

    public static function shutdown(): void {
        self::$singletonInstance = null;
    }

    public static function handle_error(): void {
    }

}
