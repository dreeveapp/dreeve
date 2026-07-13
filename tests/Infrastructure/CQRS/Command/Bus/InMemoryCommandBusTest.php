<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\CanNotRegisterCQRSHandler;
use App\Infrastructure\CQRS\Command\Bus\InMemoryCommandBus;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperation;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperationCommandHandler;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperationCommand\RunAnOperationCommandCommandHandler;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

class InMemoryCommandBusTest extends KernelTestCase
{
    public function testDispatch(): void
    {
        $commandBus = new InMemoryCommandBus(commandHandlers: [
            new RunAnOperationCommandHandler(),
        ], eventBus: new SpyEventBus());

        $this->expectExceptionObject(new \RuntimeException('This is a test command and it is called'));

        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWhenNotRegistered(): void
    {
        $commandBus = new InMemoryCommandBus(commandHandlers: [], eventBus: new SpyEventBus());

        $this->expectExceptionObject(new NoHandlerForMessageException(RunAnOperation::class));

        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithoutCorrespondingCommand(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('No corresponding object for CommandHandler "App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithoutACommandCommandHandler" found. Expected namespace: App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithoutACommand'));

        $commandBus = new InMemoryCommandBus(commandHandlers: [
            new RunOperationWithoutACommandCommandHandler(),
        ], eventBus: new SpyEventBus());
        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithInvalidCommandName(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('Object name cannot end with "Command"'));

        $commandBus = new InMemoryCommandBus(commandHandlers: [
            new RunAnOperationCommandCommandHandler(),
        ], eventBus: new SpyEventBus());
        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithInvalidCommandHandlerName(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('Fqcn "App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithInvalidNameHandler" does not end with "CommandHandler"'));

        $commandBus = new InMemoryCommandBus(commandHandlers: [
            new RunOperationWithInvalidNameHandler(),
        ], eventBus: new SpyEventBus());
        $commandBus->dispatch(new RunAnOperation('test'));
    }
}
