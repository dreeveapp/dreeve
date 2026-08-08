<?php

declare(strict_types=1);

namespace App\Infrastructure\Eventing;

use Symfony\Contracts\EventDispatcher\Event;

abstract class DomainEvent extends Event implements \JsonSerializable
{
    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'eventName' => str_replace('\\', '.', static::class),
            'payload' => $this->getSerializablePayload(),
        ];
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->getSerializablePayload() == $other->getSerializablePayload();
    }

    /**
     * @return array<mixed>
     */
    protected function getSerializablePayload(): array
    {
        $serializedPayload = [];
        foreach (new \ReflectionClass($this)->getProperties() as $property) {
            $serializedPayload[$property->getName()] = $property->getValue($this);
        }

        return $serializedPayload;
    }
}
