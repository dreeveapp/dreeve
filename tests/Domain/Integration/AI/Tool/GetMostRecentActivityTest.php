<?php

namespace App\Tests\Domain\Integration\AI\Tool;

use App\Domain\Integration\AI\Tool\GetMostRecentActivity;
use App\Domain\Integration\AI\Tool\ToolArguments;
use App\Domain\Integration\AI\Tool\ToolFailed;
use App\Tests\ContainerTestCase;

class GetMostRecentActivityTest extends ContainerTestCase
{
    public function testItShouldThrowWhenThereAreNoActivities(): void
    {
        $this->expectExceptionObject(new ToolFailed('No activities found.'));
        $this->getContainer()->get(GetMostRecentActivity::class)->__invoke(new ToolArguments([]));
    }
}
