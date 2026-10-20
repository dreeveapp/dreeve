<?php

declare(strict_types=1);

namespace App\Infrastructure\Eventing;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class SystemEventBus implements EventBus
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param DomainEvent[] $events
     */
    public function publishEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch(
                event: $event,
                eventName: $event::class
            );
        }
    }
}
