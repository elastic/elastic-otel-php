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

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ErrorException;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Throwable;

abstract class TestInfraHttpServerProcessBase extends SpawnedProcessBase
{
    use HttpServerProcessTrait;

    public const BASE_URI_PATH = '/Elastic_OTel_PHP_tests_infra/';
    public const CLEAN_TEST_SCOPED_URI_PATH = self::BASE_URI_PATH . 'clean_test_scoped';
    public const EXIT_URI_PATH = self::BASE_URI_PATH . 'exit';

    private readonly Logger $logger;
    protected ?LoopInterface $reactLoop = null;

    /** @var SocketServer[] */
    protected array $serverSockets = [];

    public function __construct()
    {
        set_error_handler(
            function (int $type, string $message, string $srcFile, int $srcLine): bool {
                throw new ErrorException(
                    message:  ExceptionUtil::buildMessage($message, ['error type' => $type, 'source code location' => $srcFile . ':' . $srcLine]),
                    code:     0,
                    severity: $type,
                    filename: $srcFile,
                    line:     $srcLine
                );
            }
        );

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        parent::__construct();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Done');
    }

    #[Override]
    protected function processConfig(): void
    {
        parent::processConfig();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $testConfig = AmbientContextForTests::testConfig();
        $dbgCtx->add(compact('testConfig'));

        Assert::assertCount($this->portsCount(), $testConfig->dataPerProcess()->thisServerPorts);

        // At this point data per request is not parsed and not applied to config yet
        Assert::assertNull($testConfig->getOptionValueByName(OptionForTestsName::data_per_request));
    }

    /**
     * @return positive-int
     */
    public static function portsCount(): int
    {
        return 1;
    }

    protected static function getPortByIndex(int $portIndex): int
    {
        return AmbientContextForTests::testConfig()->dataPerProcess()->thisServerPorts[$portIndex];
    }

    protected function onNewConnection(int $portIndex, ConnectionInterface $connection): void
    {
        $logDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logDebug?->log(
            __LINE__,
            'New connection',
            [
                'portIndex' => $portIndex,
                'connection addresses' => [
                    'remote' => $connection->getRemoteAddress(),
                    'local'  => $connection->getLocalAddress(),
                ]
            ]
        );
    }

    /**
     * @return null|ResponseInterface|Promise<ResponseInterface>
     */
    abstract protected function processRequest(int $portIndex, ServerRequestInterface $request): null|ResponseInterface|Promise;

    public static function run(): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObj): void {
                AssertEx::isInstanceOf($thisObj, self::class)->runImpl();
            }
        );
    }

    public function runImpl(): void
    {
        $this->runHttpService();
    }

    private function runHttpService(): void
    {
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $ports = AmbientContextForTests::testConfig()->dataPerProcess()->thisServerPorts;
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Running HTTP service...', compact('ports'));

        $this->reactLoop = Loop::get();
        Assert::assertNotEmpty($ports);
        foreach (IterableUtil::zipOneWithIndex($ports) as [$portIndex, $port]) {
            $uri = HttpServerHandle::SERVER_LOCALHOST_ADDRESS . ':' . $port;
            $serverSocket = new SocketServer($uri, /* context */ [], $this->reactLoop);
            $this->serverSockets[] = $serverSocket;
            $serverSocket->on(
                'connection' /* <- event */,
                function (ConnectionInterface $connection) use ($portIndex): void {
                    $this->onNewConnection($portIndex, $connection);
                }
            );
            $httpServer = new HttpServer(
                /**
                 * @return ResponseInterface|Promise<ResponseInterface>
                 */
                function (ServerRequestInterface $request) use ($portIndex): ResponseInterface|Promise {
                    return $this->processRequestWrapper($portIndex, $request);
                }
            );
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Listening for incoming requests...', ['serverSocket address' => $serverSocket->getAddress()]);
            $httpServer->listen($serverSocket);
        }
        Assert::assertSame(count($this->serverSockets), self::portsCount());

        $this->beforeLoopRun();

        AssertEx::notNull($this->reactLoop)->run();
    }

    protected function beforeLoopRun(): void
    {
    }

    protected function shouldRequestHaveSpawnedProcessInternalId(ServerRequestInterface $request): bool
    {
        return true;
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function processRequestWrapper(int $portIndex, ServerRequestInterface $request): Promise|ResponseInterface
    {
        $logDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logDebug?->log(__LINE__, 'Received request', ['portIndex' => $portIndex, 'URI' => $request->getUri(), 'method' => $request->getMethod(), 'target' => $request->getRequestTarget()]);

        try {
            $response = $this->processRequestWrapperImpl($portIndex, $request);

            if ($response instanceof ResponseInterface) {
                $logDebug?->log(
                    __LINE__,
                    'Sending response ...',
                    ['statusCode' => $response->getStatusCode(), 'reasonPhrase' => $response->getReasonPhrase(), 'body' => $response->getBody()]
                );
            } else {
                Assert::assertInstanceOf(Promise::class, $response); // @phpstan-ignore staticMethod.alreadyNarrowedType
                $logDebug?->log(__LINE__, 'Promise returned - response will be returned later...');
            }

            return $response;
        } catch (Throwable $throwable) {
            $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__)?->log('processRequest() exited by exception - terminating this process', compact('throwable'));
            exit(self::FAILURE_PROCESS_EXIT_CODE);
        }
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function processRequestWrapperImpl(int $portIndex, ServerRequestInterface $request): Promise|ResponseInterface
    {
        if ($this->shouldRequestHaveSpawnedProcessInternalId($request)) {
            $testConfigForRequest = ConfigUtilForTests::read(
                new RequestHeadersRawSnapshotSource(
                    function (string $headerName) use ($request): ?string {
                        return self::getRequestHeader($request, $headerName);
                    }
                ),
                AmbientContextForTests::loggerFactory()
            );

            $verifySpawnedProcessInternalIdResponse = self::verifySpawnedProcessInternalId(
                $testConfigForRequest->dataPerRequest()->spawnedProcessInternalId
            );
            if ($verifySpawnedProcessInternalIdResponse !== null) {
                return $verifySpawnedProcessInternalIdResponse;
            }
        }

        if ($request->getUri()->getPath() === HttpServerHandle::STATUS_CHECK_URI_PATH) {
            return self::buildResponseWithPid();
        } elseif ($request->getUri()->getPath() === self::EXIT_URI_PATH) {
            $this->exit();
            return self::buildDefaultResponse();
        }

        if (($response = $this->processRequest($portIndex, $request)) !== null) {
            return $response;
        }

        return self::buildErrorResponse(HttpStatusCodes::BAD_REQUEST, 'Unknown URI path: `' . $request->getRequestTarget() . '\'');
    }

    protected function exit(): void
    {
        foreach ($this->serverSockets as $serverSocket) {
            $serverSocket->close();
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...');
    }

    protected static function getRequestHeader(ServerRequestInterface $request, string $headerName): ?string
    {
        $headerValues = $request->getHeader($headerName);
        if (ArrayUtilForTests::isEmpty($headerValues)) {
            return null;
        }
        if (count($headerValues) !== 1) {
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('The header should not have more than one value', compact('headerName', 'headerValues')));
        }
        return $headerValues[0];
    }

    protected static function getRequiredRequestHeader(ServerRequestInterface $request, string $headerName): string
    {
        $headerValue = self::getRequestHeader($request, $headerName);
        if ($headerValue === null) {
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Missing required HTTP request header', compact('headerName')));
        }
        return $headerValue;
    }
}
