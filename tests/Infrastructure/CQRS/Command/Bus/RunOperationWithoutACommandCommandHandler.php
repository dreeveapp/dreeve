<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class RunOperationWithoutACommandCommandHandler implements CommandHandler
{
    public function handle(Command $command): void
    {
    }
}
