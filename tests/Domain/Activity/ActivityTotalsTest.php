<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityTotals;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTotalsTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'provideActivityTotals')]
    public function testGetTotalDaysSinceFirstActivity(string $expectedResult, Activities $activities, SerializableDateTime $now): void
    {
        $this->assertEquals(
            $expectedResult,
            ActivityTotals::create(
                activities: $activities,
                now: $now,
                translator: $this->getContainer()->get(TranslatorInterface::class),
            )->getTotalDaysSinceFirstActivity()
        );
    }

    public static function provideActivityTotals(): array
    {
        return [
            [
                '1 day',
                Activities::fromArray([
                    ActivityBuilder::fromDefaults()
                        ->withStartDateTime(SerializableDateTime::fromString('2023-11-24'))
                        ->build(),
                ]),
                SerializableDateTime::fromString('2023-11-25'),
            ],
            [
                '3 weeks and 3 days',
                Activities::fromArray([
                    ActivityBuilder::fromDefaults()
                        ->withStartDateTime(SerializableDateTime::fromString('2023-11-01'))
                        ->build(),
                ]),
                SerializableDateTime::fromString('2023-11-25'),
            ],
            [
                '7 months and 1 day',
                Activities::fromArray([
                    ActivityBuilder::fromDefaults()
                        ->withStartDateTime(SerializableDateTime::fromString('2023-04-24'))
                        ->build(),
                ]),
                SerializableDateTime::fromString('2023-11-25'),
            ],
            [
                '1 year and 1 day',
                Activities::fromArray([
                    ActivityBuilder::fromDefaults()
                        ->withStartDateTime(SerializableDateTime::fromString('2022-11-24'))
                        ->build(),
                ]),
                SerializableDateTime::fromString('2023-11-25'),
            ],
            [
                '6 years and 1 day',
                Activities::fromArray([
                    ActivityBuilder::fromDefaults()
                        ->withStartDateTime(SerializableDateTime::fromString('2017-11-24'))
                        ->build(),
                ]),
                SerializableDateTime::fromString('2023-11-25'),
            ],
        ];
    }
}
