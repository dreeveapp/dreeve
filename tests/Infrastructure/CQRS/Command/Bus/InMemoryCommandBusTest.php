<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\CanNotRegisterCQRSHandler;
use App\Infrastructure\CQRS\Command\Bus\InMemoryCommandBus;
use App\Infrastructure\CQRS\Command\Bus\TransactionalCommandMiddleware;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperation;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperationCommandHandler;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperationCommand\RunAnOperationCommandCommandHandler;
use App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFail\StoreAValueAndFail;
use App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFail\StoreAValueAndFailCommandHandler;
use App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFailFromPayload\StoreAValueAndFailFromPayload;
use App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFailFromPayload\StoreAValueAndFailFromPayloadCommandHandler;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

class InMemoryCommandBusTest extends ContainerTestCase
{
    public function testDispatch(): void
    {
        $commandBus = new InMemoryCommandBus(
            commandHandlers: [
                new RunAnOperationCommandHandler(),
            ],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );

        $this->expectExceptionObject(new \RuntimeException('This is a test command and it is called'));

        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWhenNotRegistered(): void
    {
        $commandBus = new InMemoryCommandBus(
            commandHandlers: [],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );

        $this->expectExceptionObject(new NoHandlerForMessageException(RunAnOperation::class));

        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithoutCorrespondingCommand(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('No corresponding object for CommandHandler "App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithoutACommandCommandHandler" found. Expected namespace: App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithoutACommand'));

        $commandBus = new InMemoryCommandBus(
            commandHandlers: [
                new RunOperationWithoutACommandCommandHandler(),
            ],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );
        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithInvalidCommandName(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('Object name cannot end with "Command"'));

        $commandBus = new InMemoryCommandBus(
            commandHandlers: [
                new RunAnOperationCommandCommandHandler(),
            ],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );
        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testDispatchWithInvalidCommandHandlerName(): void
    {
        $this->expectExceptionObject(new CanNotRegisterCQRSHandler('Fqcn "App\\Tests\\Infrastructure\\CQRS\\Command\\Bus\\RunOperationWithInvalidNameHandler" does not end with "CommandHandler"'));

        $commandBus = new InMemoryCommandBus(
            commandHandlers: [
                new RunOperationWithInvalidNameHandler(),
            ],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );
        $commandBus->dispatch(new RunAnOperation('test'));
    }

    public function testItRollsBackAFailingCommandThatWasDeserializedFromAPayload(): void
    {
        $commandBus = new InMemoryCommandBus(
            commandHandlers: [new StoreAValueAndFailFromPayloadCommandHandler($this->getConnection())],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );

        try {
            $commandBus->dispatch(StoreAValueAndFailFromPayload::fromPayload([]));
            $this->fail('The command should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Storing a value failed', $e->getMessage());
        }

        $this->assertFalse(
            $this->getConnection()->executeQuery('SELECT value FROM KeyValue WHERE "key" = :key', ['key' => 'stored'])->fetchOne()
        );
    }

    public function testItKeepsWhatAFailingInternalCommandAlreadyStored(): void
    {
        $commandBus = new InMemoryCommandBus(
            commandHandlers: [new StoreAValueAndFailCommandHandler($this->getConnection())],
            transactionalCommandMiddleware: new TransactionalCommandMiddleware($this->getConnection()),
        );

        try {
            $commandBus->dispatch(new StoreAValueAndFail());
            $this->fail('The command should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Storing a value failed', $e->getMessage());
        }

        $this->assertSame(
            'value',
            $this->getConnection()->executeQuery('SELECT value FROM KeyValue WHERE "key" = :key', ['key' => 'stored'])->fetchOne()
        );
    }
}
