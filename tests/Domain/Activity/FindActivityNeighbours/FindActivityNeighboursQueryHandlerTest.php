<?php

namespace App\Tests\Domain\Activity\FindActivityNeighbours;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\FindActivityNeighbours\FindActivityNeighbours;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindActivityNeighboursQueryHandlerTest extends ContainerTestCase
{
    private QueryBus $queryBus;

    public function testHandle(): void
    {
        foreach ([
            ['oldest', '2024-01-01 08:00:00'],
            ['b-tied', '2024-01-02 08:00:00'],
            ['a-tied', '2024-01-02 08:00:00'],
            ['newest', '2024-01-03 08:00:00'],
        ] as [$activityId, $startDateTime]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withName('Activity '.$activityId)
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
        }

        $response = $this->queryBus->ask(new FindActivityNeighbours(ActivityId::fromUnprefixed('oldest')));
        $this->assertNull($response->getOlderActivityId());
        $this->assertNull($response->getOlderActivityName());
        $this->assertEquals(ActivityId::fromUnprefixed('a-tied'), $response->getNewerActivityId());
        $this->assertEquals('Activity a-tied', $response->getNewerActivityName());

        $response = $this->queryBus->ask(new FindActivityNeighbours(ActivityId::fromUnprefixed('a-tied')));
        $this->assertEquals(ActivityId::fromUnprefixed('oldest'), $response->getOlderActivityId());
        $this->assertEquals('Activity oldest', $response->getOlderActivityName());
        $this->assertEquals(ActivityId::fromUnprefixed('b-tied'), $response->getNewerActivityId());
        $this->assertEquals('Activity b-tied', $response->getNewerActivityName());

        $response = $this->queryBus->ask(new FindActivityNeighbours(ActivityId::fromUnprefixed('b-tied')));
        $this->assertEquals(ActivityId::fromUnprefixed('a-tied'), $response->getOlderActivityId());
        $this->assertEquals(ActivityId::fromUnprefixed('newest'), $response->getNewerActivityId());

        $response = $this->queryBus->ask(new FindActivityNeighbours(ActivityId::fromUnprefixed('newest')));
        $this->assertEquals(ActivityId::fromUnprefixed('b-tied'), $response->getOlderActivityId());
        $this->assertEquals('Activity b-tied', $response->getOlderActivityName());
        $this->assertNull($response->getNewerActivityId());
        $this->assertNull($response->getNewerActivityName());
    }

    public function testHandleForTheOnlyActivity(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('only'))
                ->build(), []
        ));

        $response = $this->queryBus->ask(new FindActivityNeighbours(ActivityId::fromUnprefixed('only')));

        $this->assertNull($response->getOlderActivityId());
        $this->assertNull($response->getOlderActivityName());
        $this->assertNull($response->getNewerActivityId());
        $this->assertNull($response->getNewerActivityName());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBus = $this->getContainer()->get(QueryBus::class);
    }
}
