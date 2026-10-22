<?php

namespace App\Tests\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Integration\AI\Tool\GetAllActivitiesSummary;
use App\Domain\Integration\AI\Tool\ToolArguments;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class GetAllActivitiesSummaryTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'provideSummaries')]
    public function testItSummarizes(?int $limit, ?string $from, ?string $to, array $expectedIds): void
    {
        foreach (['1' => '2025-01-01 08:00:00', '2' => '2025-01-15 08:00:00', '3' => '2025-02-01 23:30:00', '4' => '2025-03-01 08:00:00'] as $id => $startDateTime) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $id))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(),
                []
            ));
        }

        $summary = $this->getContainer()->get(GetAllActivitiesSummary::class)->__invoke(new ToolArguments([
            'limit' => $limit,
            'from' => $from,
            'to' => $to,
        ]));

        $this->assertSame($expectedIds, array_column($summary['activitiesSummary'], 'id'));
        $this->assertSame(4, $summary['totalActivityCount']);
    }

    public static function provideSummaries(): iterable
    {
        yield 'defaults' => [null, null, null, ['4', '3', '2', '1']];
        yield 'limit keeps the newest' => [2, null, null, ['4', '3']];
        yield 'limit below one is clamped' => [0, null, null, ['4']];
        yield 'limit above max is clamped' => [1000, null, null, ['4', '3', '2', '1']];
        yield 'inclusive date range' => [null, '2025-01-15', '2025-02-01', ['3', '2']];
        yield 'only from' => [null, '2025-02-01', null, ['4', '3']];
        yield 'only to' => [null, null, '2025-01-14', ['1']];
        yield 'limit within date range' => [1, '2025-01-01', '2025-02-28', ['3']];
    }
}
