<?php

namespace App\Infrastructure\Eventing;

trait RecordsEvents
{
    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    protected function recordOnlyOnce(DomainEvent $event): void
    {
        foreach ($this->recordedEvents as $recordedEvent) {
            if ($recordedEvent->equals($event)) {
                return;
            }
        }

        $this->recordThat($event);
    }

    /**
     * @return DomainEvent[]
     */
    public function getRecordedEvents(): array
    {
        $recordedEvents = $this->recordedEvents;
        $this->recordedEvents = [];

        return $recordedEvents;
    }
}
