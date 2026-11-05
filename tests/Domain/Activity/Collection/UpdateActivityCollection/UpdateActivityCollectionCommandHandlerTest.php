<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\UpdateActivityCollection\UpdateActivityCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activity-collections/old.jpg', 'old-content');
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->withDescription('Spring cycling week')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
            ->withCoverImagePath('/files/activity-collections/old.jpg')
            ->build());

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => '  Transalp 2025  ',
            'type' => 'event',
            'description' => '   ',
            'coverImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/activity-collections/old.jpg'],
            ]),
        ]));

        $activityCollection = $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'));
        $this->assertSame('Transalp 2025', $activityCollection->getName());
        $this->assertNull($activityCollection->getDescription());
        $this->assertSame(ActivityCollectionType::EVENT, $activityCollection->getType());
        $this->assertEquals(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]), $activityCollection->getActivityIds());
        $this->assertNull($activityCollection->getCoverImagePath());
        $this->assertFalse($fileStorage->fileExists('activity-collections/old.jpg'));
    }

    public function testHandleReplacesTheCoverImage(): void
    {
        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activity-collections/old.jpg', 'old-content');
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withCoverImagePath('/files/activity-collections/old.jpg')
            ->build());

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => 'Mallorca',
            'type' => 'trip',
            'coverImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/activity-collections/old.jpg'],
                ['status' => 'new', 'filename' => 'new.jpg', 'content' => base64_encode('new-content')],
            ]),
        ]));

        $this->assertSame(
            '/files/activity-collections/0025176c-5652-11ee-923d-02424dd627d5.jpg',
            $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'))->getCoverImagePath(),
        );
        $this->assertFalse($fileStorage->fileExists('activity-collections/old.jpg'));
        $this->assertSame('new-content', $fileStorage->read('activity-collections/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
            'name' => 'Mallorca',
            'type' => 'trip',
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateActivityCollection::fromPayload($payload);
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
    }
}
