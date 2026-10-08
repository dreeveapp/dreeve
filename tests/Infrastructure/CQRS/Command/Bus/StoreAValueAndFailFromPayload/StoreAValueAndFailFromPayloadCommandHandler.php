<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFailFromPayload;

use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Doctrine\DBAL\Connection;

final readonly class StoreAValueAndFailFromPayloadCommandHandler implements CommandHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof StoreAValueAndFailFromPayload);

        $this->connection->executeStatement('INSERT INTO KeyValue ("key", value) VALUES (:key, :value)', ['key' => 'stored', 'value' => 'value']);

        throw new \RuntimeException('Storing a value failed');
    }
}
