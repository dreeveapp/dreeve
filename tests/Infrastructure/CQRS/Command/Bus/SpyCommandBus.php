<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;

class SpyCommandBus implements CommandBus
{
    /** @var list<Command> */
    private array $commandsToDispatch = [];

    public function dispatch(Command $command): void
    {
        $this->commandsToDispatch[] = $command;
    }

    /**
     * @return list<Command>
     */
    public function getDispatchedCommands(): array
    {
        $commandsToDispatch = $this->commandsToDispatch;
        $this->commandsToDispatch = [];

        return $commandsToDispatch;
    }

    /**
     * @return list<class-string<Command>>
     */
    public function getAvailableCommands(): array
    {
        return [];
    }
}
