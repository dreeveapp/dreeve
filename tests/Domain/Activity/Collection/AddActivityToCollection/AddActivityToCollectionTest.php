<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\AddActivityToCollection;

use App\Domain\Activity\Collection\AddActivityToCollection\AddActivityToCollection;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddActivityToCollectionTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        AddActivityToCollection::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing collection id' => [
            ['activityId' => 'activity-1'],
            'An "activityCollectionId" and "activityId" are required.',
        ];

        yield 'missing activity id' => [
            ['activityCollectionId' => 'activityCollection-1'],
            'An "activityCollectionId" and "activityId" are required.',
        ];

        yield 'typed text without an activity id' => [
            ['activityCollectionId' => 'activityCollection-1', 'activityId' => 'Morning ride'],
            'The "activityId" does not contain a valid activity id.',
        ];
    }
}
