<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\AddActivityCollection;

use App\Domain\Activity\Collection\AddActivityCollection\AddActivityCollection;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddActivityCollectionTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        AddActivityCollection::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing id' => [
            ['name' => 'Mallorca', 'type' => 'trip'],
            'An "activityCollectionId" and "name" are required.',
        ];

        yield 'missing name' => [
            ['activityCollectionId' => 'activityCollection-1', 'type' => 'trip'],
            'An "activityCollectionId" and "name" are required.',
        ];

        yield 'empty name' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => '   ', 'type' => 'trip'],
            'The name cannot be empty.',
        ];

        yield 'missing type' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca'],
            'The type is invalid.',
        ];

        yield 'invalid type' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'training-camp'],
            'The type is invalid.',
        ];

        yield 'unsupported cover image type' => [
            [
                'activityCollectionId' => 'activityCollection-1',
                'name' => 'Mallorca',
                'type' => 'trip',
                'coverImagePath' => json_encode([
                    ['status' => 'new', 'filename' => 'cover.gif', 'content' => base64_encode('image-content')],
                ]),
            ],
            'Unsupported image file type.',
        ];
    }
}
