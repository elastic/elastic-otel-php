<?php

/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\OTel\Traces;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Psr7Server\ServerRequestCreator;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Version;
use Psr\Http\Message\ServerRequestInterface;
use Elastic\OTel\Util\WildcardListMatcher;

class ElasticRootSpan
{
    use LogsMessagesTrait;

    public static function startRootSpan()
    {
        if (php_sapi_name() === 'cli') {
            if (!Configuration::getBoolean('ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI', true)) {
                self::logDebug('ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI set to false');
                return;
            }
        } else if (!Configuration::getBoolean('ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED', true)) {
            self::logDebug('ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED set to false');
            return;
        }

        $request = self::createRequest();
        if ($request) {
            self::create($request);
            self::registerShutdownHandler();
        } else {
            self::logWarning('Unable to create server request');
        }
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     * @internal
     */
    private static function create(ServerRequestInterface $request): void
    {
        $tracer = Globals::tracerProvider()->getTracer(
            'co.elastic.php.elastic-root-span',
            null,
            Version::VERSION_1_25_0->url(),
        );
        $parent = Globals::propagator()->extract($request->getHeaders());
        $startTime = array_key_exists('REQUEST_TIME_FLOAT', $request->getServerParams())
            ? $request->getServerParams()['REQUEST_TIME_FLOAT']
            : (int) microtime(true);
        $span = $tracer->spanBuilder(self::getSpanName($request))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setStartTimestamp((int) ($startTime * 1_000_000_000))
            ->setParent($parent)
            ->setAttribute(TraceAttributes::URL_FULL, (string) $request->getUri())
            ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
            ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->getHeaderLine('Content-Length'))
            ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))
            ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
            ->setAttribute(TraceAttributes::SERVER_PORT, $request->getUri()->getPort())
            ->setAttribute(TraceAttributes::URL_SCHEME, $request->getUri()->getScheme())
            ->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())
            ->startSpan();
        Context::storage()->attach($span->storeInContext($parent));
    }

    /**
     * @internal
     */
    private static function createRequest(): ?ServerRequestInterface
    {
        try {
            $creator = new ServerRequestCreator(
                Psr17FactoryDiscovery::findServerRequestFactory(),
                Psr17FactoryDiscovery::findUriFactory(),
                Psr17FactoryDiscovery::findUploadedFileFactory(),
                Psr17FactoryDiscovery::findStreamFactory(),
            );

            return $creator->fromGlobals();
        } catch (NotFoundException $e) {
            self::logError('Unable to initialize server request creator for auto root span creation', ['exception' => $e]);
        }

        return null;
    }

    /**
     * @internal
     */
    private static function registerShutdownHandler(): void
    {
        ShutdownHandler::register(self::shutdownHandler(...));
    }

    /**
     * @internal
     */
    public static function shutdownHandler(): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            self::logDebug('Root span not created or ended too early');
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        $span->end();
    }

    private static function getSpanName(ServerRequestInterface $request)
    {
        if (php_sapi_name() === 'cli') {
            return self::processPathMatchers($_SERVER['SCRIPT_NAME']);
        }

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        return $method . " " . self::processPathMatchers($path);
    }

    private static function processPathMatchers(string $path): string
    {
        $groups = Configuration::getList('ELASTIC_OTEL_TRANSACTION_URL_GROUPS', []);
        if ($groups === null || count($groups) == 0) {
            return $path;
        }

        $matcher = new WildcardListMatcher($groups);
        return $matcher->match($path) ?? $path;
    }
}