<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\Command\Bus\SetForceRebuildFlagMiddleware;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperation;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperationThatRequiresRebuild\RunAnOperationThatRequiresRebuild;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class SetForceRebuildFlagMiddlewareTest extends TestCase
{
    private SetForceRebuildFlagMiddleware $middleware;
    private MockObject $keyValueStore;

    public function testItFlagsForceRebuildForATaggedCommand(): void
    {
        $this->keyValueStore
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (KeyValue $keyValue): bool => Key::FORCE_REBUILD === $keyValue->getKey()
                    && '1' === (string) $keyValue->getValue()
            ));

        $this->middleware->handle(new Envelope(new RunAnOperationThatRequiresRebuild()), $this->stackThatSucceeds());
    }

    public function testItDoesNotFlagForAnUntaggedCommand(): void
    {
        $this->keyValueStore
            ->expects($this->never())
            ->method('save');

        $this->middleware->handle(new Envelope(new RunAnOperation('test')), $this->stackThatSucceeds());
    }

    public function testItDoesNotFlagWhenTheCommandFails(): void
    {
        $this->keyValueStore
            ->expects($this->never())
            ->method('save');

        $this->expectExceptionObject(new \RuntimeException('Command failed'));

        $this->middleware->handle(new Envelope(new RunAnOperationThatRequiresRebuild()), $this->stackThatThrows());
    }

    private function stackThatSucceeds(): StackInterface
    {
        return new class implements StackInterface, MiddlewareInterface {
            public function next(): MiddlewareInterface
            {
                return $this;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        };
    }

    private function stackThatThrows(): StackInterface
    {
        return new class implements StackInterface, MiddlewareInterface {
            public function next(): MiddlewareInterface
            {
                return $this;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new \RuntimeException('Command failed');
            }
        };
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->createMock(KeyValueStore::class);
        $this->middleware = new SetForceRebuildFlagMiddleware($this->keyValueStore);
    }
}
