<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\DeleteActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\DeleteActivity\DeleteActivity;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DeleteActivityTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteActivity::fromPayload([
            'activityId' => 'activity-1',
        ]);

        $this->assertEquals(ActivityId::fromUnprefixed('1'), $command->getActivityId());
    }

    #[TestWith([[]])]
    #[TestWith([['activityId' => 1]])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "activityId" is required.'));

        DeleteActivity::fromPayload($payload);
    }
}
