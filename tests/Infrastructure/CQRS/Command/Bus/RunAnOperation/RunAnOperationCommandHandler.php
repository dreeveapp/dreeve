<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation;

use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class RunAnOperationCommandHandler implements CommandHandler
{
    public function handle(Command $command): void
    {
        assert($command instanceof RunAnOperation);
        throw new \RuntimeException('This is a test command and it is called');
        // Waw, such empty.
    }
}
