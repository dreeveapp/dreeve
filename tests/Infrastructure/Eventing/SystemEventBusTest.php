<?php

namespace App\Tests\Infrastructure\Eventing;

use App\Infrastructure\Eventing\SystemEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SystemEventBusTest extends TestCase
{
    public function testPublishEvents(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventBus = new SystemEventBus($eventDispatcher);

        $event = new ADomainEvent();
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $event,
                ADomainEvent::class
            );

        $eventBus->publishEvents([$event]);
    }
}
