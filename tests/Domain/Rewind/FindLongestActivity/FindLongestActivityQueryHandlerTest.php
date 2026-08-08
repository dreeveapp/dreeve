<?php

namespace App\Tests\Domain\Rewind\FindLongestActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Rewind\FindLongestActivity\FindLongestActivity;
use App\Domain\Rewind\FindLongestActivity\FindLongestActivityQueryHandler;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;

class FindLongestActivityQueryHandlerTest extends ContainerTestCase
{
    private FindLongestActivityQueryHandler $queryHandler;

    public function testHandleWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Could not determine longest activity'));
        $this->queryHandler->handle(new FindLongestActivity(Years::fromArray([Year::fromInt(2026)])));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindLongestActivityQueryHandler(
            $this->getConnection(),
            $this->getContainer()->get(ActivityRepository::class)
        );
    }
}
