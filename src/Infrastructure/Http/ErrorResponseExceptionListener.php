<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Config\PlatformEnvironment;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final readonly class ErrorResponseExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private PlatformEnvironment $platformEnvironment,
        private Environment $twig,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Symfony's own error page carries the stack trace.
        if (PlatformEnvironment::DEV === $this->platformEnvironment) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = match (true) {
            $exception instanceof NotFoundHttpException => HttpStatusCode::NOT_FOUND,
            $exception instanceof \InvalidArgumentException,
            $exception instanceof BadRequestException => HttpStatusCode::BAD_REQUEST,
            $exception instanceof TooManyRequestsHttpException => HttpStatusCode::TOO_MANY_REQUESTS,
            default => HttpStatusCode::INTERNAL_SERVER_ERROR,
        };

        $response = new HtmlResponse($this->twig->render('html/error.html.twig', [
            'statusCode' => $statusCode->value,
        ]));
        $response->setStatusCode($statusCode->value);

        $event->allowCustomResponseCode();
        $event->setResponse($response);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => [['onKernelException', -32]]];
    }
}
