<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Logging\Monolog;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[WithMonologChannel('errors')]
final readonly class ServerErrorLogger
{
    private const int REFERENCE_LENGTH = 8;

    public function __construct(
        private LoggerInterface $logger,
        private UuidFactory $uuidFactory,
    ) {
    }

    public function log(\Throwable $exception, int $statusCode, Request $request): ?string
    {
        if (Response::HTTP_INTERNAL_SERVER_ERROR !== $statusCode) {
            return null;
        }

        $reference = substr(str_replace('-', '', $this->uuidFactory->random()), 0, self::REFERENCE_LENGTH);

        $this->logger->error(
            message: new Monolog(
                $reference,
                sprintf('%s %s', $request->getMethod(), $request->getPathInfo()),
                (string) $exception,
            ),
            context: ['exception' => $exception],
        );

        return $reference;
    }
}
