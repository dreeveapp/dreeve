<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class TransactionalCommandMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->getMessage() instanceof DeserializableCommand) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->connection->transactional(
            static fn (): Envelope => $stack->next()->handle($envelope, $stack)
        );
    }
}
