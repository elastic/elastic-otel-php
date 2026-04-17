<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

use OpenTelemetry\Distro\Log\LogLevel;
use Throwable;

/**
 * @phpstan-import-type Context from BuildToolsLog
 */
trait BuildToolsLoggingClassTrait
{
    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logWithLevel(LogLevel $level, int $line, string $fqMethod, string $msg, array $context = []): void
    {
        // getCurrentSourceCodeFile() must be defined in class using BuildToolsLoggingClassTrait
        BuildToolsLog::withLevel($level, self::getCurrentSourceCodeFile(), $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logCritical(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::critical, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logError(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::error, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logWarning(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::warning, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logInfo(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::info, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logDebug(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::debug, $line, $fqMethod, $msg, $context);
    }

    /**
     * @param Context $context
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private static function logTrace(int $line, string $fqMethod, string $msg, array $context = []): void
    {
        self::logWithLevel(LogLevel::trace, $line, $fqMethod, $msg, $context);
    }

    private static function logThrowable(LogLevel $level, int $line, string $fqMethod, Throwable $throwable): void
    {
        if (!BuildToolsLog::isLevelEnabled(LogLevel::critical)) {
            return;
        }

        $getTraceEntryProp = function (array $traceEntry, string $propKey, string $defaultValue): string {
            if (!array_key_exists($propKey, $traceEntry)) {
                return $defaultValue;
            }
            $propVal = $traceEntry[$propKey];
            return is_scalar($propVal) ? strval($propVal) : $defaultValue;
        };
        self::logWithLevel($level, $line, $fqMethod, 'Caught throwable: ' . $throwable->getMessage());
        BuildToolsLog::writeLineRaw('Stack trace:');
        foreach ($throwable->getTrace() as $traceEntry) {
            $text = $getTraceEntryProp($traceEntry, 'file', '<FILE>') . ':' . $getTraceEntryProp($traceEntry, 'line', '<LINE>');
            $text .= ' (' . $getTraceEntryProp($traceEntry, 'class', '<CLASS>') . '::' . $getTraceEntryProp($traceEntry, 'function', '<FUNC>') . ')';
            self::logInfo(__LINE__, __METHOD__, "\t" . $text);
        }
    }
}
