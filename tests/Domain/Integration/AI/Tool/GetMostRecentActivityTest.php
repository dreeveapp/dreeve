<?php

namespace App\Tests\Domain\Integration\AI\Tool;

use App\Domain\Integration\AI\Tool\GetMostRecentActivity;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;

class GetMostRecentActivityTest extends ContainerTestCase
{
    public function testItShouldThrowWhenThereAreNoActivities(): void
    {
        $this->expectExceptionObject(new EntityNotFound('No activities found'));
        $this->getContainer()->get(GetMostRecentActivity::class)->__invoke();
    }
}
