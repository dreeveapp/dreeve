<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Infrastructure\Config\PlatformEnvironment;
use App\Infrastructure\Http\ServerErrorLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ApiExceptionListener implements EventSubscriberInterface
{
    private const string PATH_PREFIX = '/api/v1';

    public function __construct(
        private PlatformEnvironment $platformEnvironment,
        private ServerErrorLogger $serverErrorLogger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();

        if (self::PATH_PREFIX !== $path && !str_starts_with($path, self::PATH_PREFIX.'/')) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof \InvalidArgumentException,
            $exception instanceof BadRequestException => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $this->serverErrorLogger->log(
            exception: $exception,
            statusCode: $statusCode,
            request: $event->getRequest()
        );

        $event->allowCustomResponseCode();
        $event->setResponse(new ApiErrorResponse(
            statusCode: $statusCode,
            error: $this->errorCodeFor($statusCode),
            message: $this->messageFor($exception, $statusCode),
            headers: $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [],
        ));
    }

    private function errorCodeFor(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'invalid_token',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
            default => 'internal_error',
        };
    }

    private function messageFor(\Throwable $exception, int $statusCode): string
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getMessage();
        }

        if (PlatformEnvironment::DEV === $this->platformEnvironment) {
            return sprintf('%s: %s', $exception::class, $exception->getMessage());
        }

        return Response::HTTP_BAD_REQUEST === $statusCode
            ? 'The request could not be processed.'
            : 'Something went wrong.';
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => [['onKernelException', -16]]];
    }
}
