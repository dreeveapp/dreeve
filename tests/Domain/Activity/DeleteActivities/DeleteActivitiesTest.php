<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\DeleteActivities;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeleteActivities;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeleteActivitiesTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteActivities::fromPayload([
            'activityIds' => ['activity-1', ' activity-2 '],
        ]);

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]),
            $command->getActivityIds(),
        );
    }

    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedMessage));

        DeleteActivities::fromPayload($payload);
    }

    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing' => [[], 'A non-empty "activityIds" list is required.'];
        yield 'not a list' => [['activityIds' => 'activity-1'], 'A non-empty "activityIds" list is required.'];
        yield 'empty' => [['activityIds' => []], 'A non-empty "activityIds" list is required.'];
        yield 'keyed' => [['activityIds' => ['a' => 'activity-1']], 'A non-empty "activityIds" list is required.'];
        yield 'non-string entry' => [['activityIds' => [1]], 'Each "activityIds" entry must be a non-empty string.'];
        yield 'blank entry' => [['activityIds' => [' ']], 'Each "activityIds" entry must be a non-empty string.'];
    }
}
