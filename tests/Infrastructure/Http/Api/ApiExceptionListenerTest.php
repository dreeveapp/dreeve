<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Api;

use App\Infrastructure\Config\PlatformEnvironment;
use App\Infrastructure\Http\Api\ApiExceptionListener;
use App\Infrastructure\Http\ServerErrorLogger;
use App\Infrastructure\Serialization\Json;
use App\Tests\Infrastructure\ValueObject\Identifier\FakeUuidFactory;
use App\Tests\NullLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiExceptionListenerTest extends TestCase
{
    private ServerErrorLogger $serverErrorLogger;

    #[DataProvider('providePathsOutsideTheApi')]
    public function testItIgnoresRequestsOutsideTheApi(string $path): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Not found'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public static function providePathsOutsideTheApi(): iterable
    {
        yield 'an app page' => ['/dashboard'];
        yield 'the app root' => ['/'];
        yield 'the internal api' => ['/api/internal/fragment/page/dashboard'];
        yield 'the api root' => ['/api'];
        yield 'a prefix lookalike' => ['/api/v10/status'];
    }

    public function testItAnswersApiRequestsWithJson(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('No route found'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(
            ['error' => 'not_found', 'message' => 'No route found'],
            Json::decode((string) $response->getContent()),
        );
    }

    public function testItKeepsTheHeadersOfTheHttpException(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            new MethodNotAllowedHttpException(['POST'], 'Method Not Allowed'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('POST', $response->headers->get('Allow'));
    }

    public function testItKeepsTheWwwAuthenticateHeader(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            new UnauthorizedHttpException('Bearer realm="Dreeve"', 'Invalid token'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Bearer realm="Dreeve"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('invalid_token', Json::decode((string) $response->getContent())['error']);
    }

    public function testItMapsAnInvalidArgumentToABadRequest(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/activity/upload'),
            HttpKernelInterface::MAIN_REQUEST,
            new \InvalidArgumentException('The file name is not valid.'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(
            ['error' => 'bad_request', 'message' => 'The request could not be processed.'],
            Json::decode((string) $response->getContent()),
        );
    }

    public function testItDoesNotLeakInternalsForAnUnexpectedException(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('SQLSTATE[HY000]: near "SELECT"'),
        );

        new ApiExceptionListener(PlatformEnvironment::PROD, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame(
            ['error' => 'internal_error', 'message' => 'Something went wrong.'],
            Json::decode((string) $response->getContent()),
        );
    }

    public function testItSpellsOutUnexpectedExceptionsInDev(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('Something exploded'),
        );

        new ApiExceptionListener(PlatformEnvironment::DEV, $this->serverErrorLogger)->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(
            'RuntimeException: Something exploded',
            Json::decode((string) $response->getContent())['message'],
        );
    }

    public function testItLogsAnUnexpectedException(): void
    {
        $exception = new \RuntimeException('Something exploded');
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/status'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->anything(), ['exception' => $exception]);

        new ApiExceptionListener(
            PlatformEnvironment::PROD,
            new ServerErrorLogger($logger, new FakeUuidFactory()),
        )->onKernelException($event);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->serverErrorLogger = new ServerErrorLogger(
            new NullLogger(),
            new FakeUuidFactory(),
        );
    }
}
