<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

use OpenTelemetry\Distro\BootstrapStageStdErrWriter;
use OpenTelemetry\Distro\Log\LogFeature;
use OpenTelemetry\Distro\Log\LogLevel;
use ReflectionClass;

/**
 * @phpstan-type Context array<string, mixed>
 */
final class BuildToolsLog
{
    use BuildToolsAssertTrait;

    public const DEFAULT_LEVEL = LogLevel::info;

    private static ?LogLevel $maxEnabledLevel = null;

    public static function configure(LogLevel $maxEnabledLevel): void
    {
        self::assertNull(self::$maxEnabledLevel);
        self::$maxEnabledLevel = $maxEnabledLevel;
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnused
     */
    public static function error(string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::withLevel(LogLevel::error, $file, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnused
     */
    public static function info(string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::withLevel(LogLevel::info, $file, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnused
     */
    public static function debug(string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::withLevel(LogLevel::debug, $file, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnused
     */
    public static function trace(string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::withLevel(LogLevel::trace, $file, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     */
    public static function withLevel(LogLevel $level, string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::withLevelAndFeature($level, $level->name, /* feature */ null, $file, $line, $fqMethod, $msg, $context);
    }

    public static function shortenFqMethod(string $fqMethod): string
    {
        return str_starts_with($fqMethod, __NAMESPACE__) ? substr($fqMethod, strlen(__NAMESPACE__) + 1) : $fqMethod;
    }

    /**
     * @param Context $context
     */
    public static function withLevelAndFeature(LogLevel $level, string $levelName, ?int $feature, string $file, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        if (!self::isLevelEnabled($level)) {
            return;
        }

        $ctxSuffix = count($context) === 0 ? '' : (' ; ' . json_encode($context));
        $funcAdapted = self::shortenFqMethod($fqMethod);
        $fileAdapted = basename($file);
        $lineToWrite = '[' . strtoupper($levelName) . ']';
        if ($feature !== null) {
            $featureName = self::findProdLogFeatureName($feature) ?? "<UNKNOWN FEATURE $feature>";
            $lineToWrite .= " [$featureName]";
        }
        if ($funcAdapted !== '') {
            $lineToWrite .= " [$funcAdapted]";
        }
        $lineToWrite .= " [$fileAdapted:$line] $msg$ctxSuffix";
        self::writeLineRaw($lineToWrite);
    }

    private static function findProdLogFeatureName(int $feature): ?string
    {
        /** @var ?array<int, string> $valueToNameMap */
        static $valueToNameMap = null;
        if ($valueToNameMap === null) {
            $valueToNameMap = self::buildProdLogFeatureValueToNameMap();
        }

        return array_key_exists($feature, $valueToNameMap) ? $valueToNameMap[$feature] : null;
    }

    /**
     * @return array<int, string>
     */
    public static function buildProdLogFeatureValueToNameMap(): array
    {
        $result = [];
        $logFeatureReflClass = new ReflectionClass(LogFeature::class);
        foreach ($logFeatureReflClass->getConstants() as $constName => $constValue) {
            $result[self::assertIsInt($constValue)] = $constName;
        }
        return $result;
    }

    public static function writeAsProdSink(int $levelIntVal, int $feature, string $file, int $line, string $func, string $text): void
    {
        $foundLevel = LogLevel::tryFrom($levelIntVal);
        $levelToUse = $foundLevel === null ? BuildToolsLog::DEFAULT_LEVEL : $foundLevel;
        $levelNameToUse = $foundLevel === null ? "LEVEL $levelIntVal" : $foundLevel->name;
        self::withLevelAndFeature($levelToUse, $levelNameToUse, $feature, $file, $line, $func, $text);
    }

    public static function writeLineRaw(string $text): void
    {
        BootstrapStageStdErrWriter::writeLine($text);
    }

    public static function isLevelEnabled(LogLevel $level): bool
    {
        return self::getMaxEnabledLevel()->value >= $level->value;
    }

    public static function getMaxEnabledLevel(): LogLevel
    {
        self::assertNotNull(self::$maxEnabledLevel);
        return self::$maxEnabledLevel;
    }
}
