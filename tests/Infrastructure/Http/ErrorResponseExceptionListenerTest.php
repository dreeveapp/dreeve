<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Config\PlatformEnvironment;
use App\Infrastructure\Http\ErrorResponseExceptionListener;
use App\Infrastructure\Http\HttpStatusCode;
use App\Tests\ContainerTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

class ErrorResponseExceptionListenerTest extends ContainerTestCase
{
    private ErrorResponseExceptionListener $errorResponseExceptionListener;

    #[\PHPUnit\Framework\Attributes\DataProvider('provideExceptions')]
    public function testItRendersAnHtmlPageForTheMatchingStatusCode(\Throwable $exception, HttpStatusCode $expectedStatusCode): void
    {
        $event = $this->exceptionEvent($exception);

        $this->errorResponseExceptionListener->onKernelException($event);

        $response = $event->getResponse();
        self::assertTrue($event->isAllowingCustomResponseCode());
        self::assertEquals($expectedStatusCode->value, $response->getStatusCode());
        self::assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString((string) $expectedStatusCode->value, (string) $response->getContent());
    }

    public static function provideExceptions(): iterable
    {
        yield 'not found' => [new NotFoundHttpException('Unknown settings group "bogus"'), HttpStatusCode::NOT_FOUND];
        yield 'invalid argument' => [new \InvalidArgumentException(), HttpStatusCode::BAD_REQUEST];
        yield 'anything else' => [new \RuntimeException('A message'), HttpStatusCode::INTERNAL_SERVER_ERROR];
    }

    public function testItRendersTheNotFoundCopyForA404(): void
    {
        $event = $this->exceptionEvent(new NotFoundHttpException('Not found'));

        $this->errorResponseExceptionListener->onKernelException($event);

        self::assertStringContainsString('wandered off the map', (string) $event->getResponse()->getContent());
    }

    public function testItDoesNotLeakTheExceptionMessage(): void
    {
        $event = $this->exceptionEvent(new NotFoundHttpException('Unknown settings group "bogus"'));

        $this->errorResponseExceptionListener->onKernelException($event);

        self::assertStringNotContainsString('bogus', (string) $event->getResponse()->getContent());
    }

    public function testItStepsAsideInDevSoSymfonyRendersItsOwnErrorPage(): void
    {
        $listener = new ErrorResponseExceptionListener(
            PlatformEnvironment::DEV,
            $this->getContainer()->get(Environment::class),
        );
        $event = $this->exceptionEvent(new \RuntimeException('A message'));

        $listener->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function exceptionEvent(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->errorResponseExceptionListener = new ErrorResponseExceptionListener(
            PlatformEnvironment::PROD,
            $this->getContainer()->get(Environment::class),
        );
    }
}
