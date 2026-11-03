<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperationCommand;

use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class RunAnOperationCommandCommandHandler implements CommandHandler
{
    public function handle(Command $command): void
    {
    }
}
