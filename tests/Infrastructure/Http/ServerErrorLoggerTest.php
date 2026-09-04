<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\ServerErrorLogger;
use App\Tests\Infrastructure\ValueObject\Identifier\FakeUuidFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ServerErrorLoggerTest extends TestCase
{
    private ServerErrorLogger $serverErrorLogger;
    private MockObject $logger;

    public function testItLogsTheExceptionForAServerError(): void
    {
        $exception = new \RuntimeException('Something exploded');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->callback(fn (\Stringable $message): bool => str_starts_with((string) $message, '0025176c - GET /activities/activity-123 - RuntimeException: Something exploded in ')
                    && str_contains((string) $message, 'Stack trace:')),
                ['exception' => $exception]
            );

        $reference = $this->serverErrorLogger->log(
            $exception,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Request::create('/activities/activity-123')
        );

        $this->assertEquals('0025176c', $reference);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideNonServerErrorStatusCodes')]
    public function testItIgnoresAnythingThatIsNotAServerError(int $statusCode): void
    {
        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->assertNull($this->serverErrorLogger->log(
            new \RuntimeException('Something exploded'),
            $statusCode,
            Request::create('/activities/activity-123')
        ));
    }

    public static function provideNonServerErrorStatusCodes(): iterable
    {
        yield 'not found' => [Response::HTTP_NOT_FOUND];
        yield 'bad request' => [Response::HTTP_BAD_REQUEST];
        yield 'too many requests' => [Response::HTTP_TOO_MANY_REQUESTS];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->serverErrorLogger = new ServerErrorLogger(
            $this->logger = $this->createMock(LoggerInterface::class),
            new FakeUuidFactory(),
        );
    }
}
