<?php

namespace App\Tests\Infrastructure\Eventing;

use App\Infrastructure\Eventing\DomainEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainEventTest extends TestCase
{
    #[DataProvider('provideEvents')]
    public function testEquals(DomainEvent $event, DomainEvent $other, bool $expected): void
    {
        $this->assertSame($expected, $event->equals($other));
    }

    public static function provideEvents(): iterable
    {
        yield 'same class and payload' => [new ADomainEventWithAPayload('one'), new ADomainEventWithAPayload('one'), true];
        yield 'no payload at all' => [new ADomainEvent(), new ADomainEvent(), true];
        yield 'payload differs' => [new ADomainEventWithAPayload('one'), new ADomainEventWithAPayload('other'), false];
        yield 'class differs' => [new ADomainEvent(), new ADomainEventWithAPayload('one'), false];
    }
}
