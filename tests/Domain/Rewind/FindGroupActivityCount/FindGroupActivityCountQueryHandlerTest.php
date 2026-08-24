<?php

namespace App\Tests\Domain\Rewind\FindGroupActivityCount;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Rewind\FindGroupActivityCount\FindGroupActivityCount;
use App\Domain\Rewind\FindGroupActivityCount\FindGroupActivityCountQueryHandler;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindGroupActivityCountQueryHandlerTest extends ContainerTestCase
{
    private FindGroupActivityCountQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withIsGroupActivity(true)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-06-01 00:00:00'))
                ->withIsGroupActivity(true)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-09-01 00:00:00'))
                ->withIsGroupActivity(false)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 00:00:00'))
                ->withIsGroupActivity(true)
                ->build(),
            []
        ));

        /** @var \App\Domain\Rewind\FindGroupActivityCount\FindGroupActivityCountResponse $response */
        $response = $this->queryHandler->handle(new FindGroupActivityCount(Years::fromArray([Year::fromInt(2024)])));
        $this->assertEquals(2, $response->getGroupActivityCount());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindGroupActivityCountQueryHandler($this->getConnection());
    }
}
