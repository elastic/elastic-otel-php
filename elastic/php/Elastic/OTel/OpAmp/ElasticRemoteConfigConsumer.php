<?php

declare(strict_types=1);

namespace Elastic\OTel\OpAmp;

use OpenTelemetry\Distro\RemoteConfigConsumerInterface;

/**
 * Elastic OpAMP remote config consumer.
 *
 * Looks for the 'elastic' key in the OpAMP file map, decodes it as JSON,
 * and delegates to ElasticRemoteConfigParser.
 *
 * @internal
 */
final class ElasticRemoteConfigConsumer implements RemoteConfigConsumerInterface
{
    private const REMOTE_CONFIG_FILE_NAME = 'elastic';

    /**
     * @param array<string, string> $fileNameToContent
     */
    public function applyRemoteConfig(array $fileNameToContent): void
    {
        if (!array_key_exists(self::REMOTE_CONFIG_FILE_NAME, $fileNameToContent)) {
            return;
        }

        $content = $fileNameToContent[self::REMOTE_CONFIG_FILE_NAME];
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            error_log('[EDOT] [ERROR] Failed to decode remote config JSON for key "' . self::REMOTE_CONFIG_FILE_NAME . '": ' . json_last_error_msg());
            return;
        }

        ElasticRemoteConfigParser::parseAndApply($decoded);
    }
}
