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
            error: match ($statusCode) {
                Response::HTTP_BAD_REQUEST => ApiErrorCode::BAD_REQUEST,
                Response::HTTP_UNAUTHORIZED => ApiErrorCode::INVALID_TOKEN,
                Response::HTTP_FORBIDDEN => ApiErrorCode::FORBIDDEN,
                Response::HTTP_NOT_FOUND => ApiErrorCode::NOT_FOUND,
                Response::HTTP_METHOD_NOT_ALLOWED => ApiErrorCode::METHOD_NOT_ALLOWED,
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE => ApiErrorCode::UNSUPPORTED_MEDIA_TYPE,
                Response::HTTP_TOO_MANY_REQUESTS => ApiErrorCode::TOO_MANY_REQUESTS,
                default => ApiErrorCode::INTERNAL_ERROR,
            },
            message: match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getMessage(),
                PlatformEnvironment::DEV === $this->platformEnvironment => sprintf('%s: %s', $exception::class, $exception->getMessage()),
                Response::HTTP_BAD_REQUEST === $statusCode => 'The request could not be processed.',
                default => 'Something went wrong.',
            },
            headers: $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [],
        ));
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => [['onKernelException', -16]]];
    }
}
