<?php

namespace App\Tests\Infrastructure\Eventing;

use PHPUnit\Framework\TestCase;

class DomainEventTest extends TestCase
{
    public function testEqualsWhenTheClassAndThePayloadMatch(): void
    {
        $this->assertTrue(
            new ADomainEventWithAPayload('one')->equals(new ADomainEventWithAPayload('one'))
        );
    }

    public function testEqualsWhenThereIsNoPayloadAtAll(): void
    {
        $this->assertTrue(new ADomainEvent()->equals(new ADomainEvent()));
    }

    public function testDoesNotEqualWhenThePayloadDiffers(): void
    {
        $this->assertFalse(
            new ADomainEventWithAPayload('one')->equals(new ADomainEventWithAPayload('other'))
        );
    }

    public function testDoesNotEqualWhenTheClassDiffers(): void
    {
        $this->assertFalse(new ADomainEvent()->equals(new ADomainEventWithAPayload('one')));
    }
}
