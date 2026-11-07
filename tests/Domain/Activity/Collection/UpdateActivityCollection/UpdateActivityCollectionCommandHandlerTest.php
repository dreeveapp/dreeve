<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\UpdateActivityCollection\UpdateActivityCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        foreach ([
            '1' => '2025-06-01 08:00:00',
            '2' => '2025-06-02 08:00:00',
            '3' => '2025-06-11 08:00:00',
            'before-range' => '2025-06-09 23:59:59',
            'range-start' => '2025-06-10 00:00:00',
            'range-end' => '2025-06-12 23:59:59',
            'after-range' => '2025-06-13 00:00:00',
        ] as $activityId => $startDateTime) {
            $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed((string) $activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->build(), []));
        }
        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activity-collections/old.jpg', 'old-content');
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->withDescription('Spring cycling week')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]))
            ->withCoverImagePath('/files/activity-collections/old.jpg')
            ->build());

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => '  Transalp 2025  ',
            'type' => 'event',
            'description' => '   ',
            'activityIds' => ['activity-3', 'activity-3'],
            'activityIdToAdd' => 'Col du Tourmalet (activity-2)',
            'addActivitiesBy' => 'activity',
            'coverImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/activity-collections/old.jpg'],
            ]),
        ]));

        $activityCollection = $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'));
        $this->assertSame('Transalp 2025', $activityCollection->getName());
        $this->assertNull($activityCollection->getDescription());
        $this->assertSame(ActivityCollectionType::EVENT, $activityCollection->getType());
        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('3'), ActivityId::fromUnprefixed('2')]),
            $activityCollection->getActivityIds(),
        );
        $this->assertNull($activityCollection->getCoverImagePath());
        $this->assertFalse($fileStorage->fileExists('activity-collections/old.jpg'));

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => 'Transalp 2025',
            'type' => 'event',
            'activityIds' => ['activity-3', 'activity-2'],
            'activityIdToAdd' => '',
            'addActivitiesFrom' => '2025-06-10',
            'addActivitiesTill' => '2025-06-12',
        ]));

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('3'),
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('range-end'),
                ActivityId::fromUnprefixed('range-start'),
            ]),
            $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'))->getActivityIds(),
        );
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

    public function testHandleThrowsWhenAnActivityDoesNotExist(): void
    {
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build());

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The selected activity does not exist.'));

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => 'Mallorca',
            'type' => 'trip',
            'activityIdToAdd' => 'activity-unknown',
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

        yield 'activity ids not a list' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'activityIds' => 'activity-1'],
            'The "activityIds" must be a list.',
        ];

        yield 'activity reference in activity ids' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'activityIds' => ['Col du Tourmalet (activity-2)']],
            'The "activityIds" contain an invalid activity id.',
        ];

        yield 'invalid activity to add' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'activityIdToAdd' => 'Col du Tourmalet'],
            'The "activityIdToAdd" is an invalid activity id.',
        ];

        yield 'activity and date range' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'activityIdToAdd' => 'activity-1', 'addActivitiesFrom' => '2025-06-10', 'addActivitiesTill' => ''],
            'Add one activity or a date range, not both.',
        ];

        yield 'date range without an end date' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'addActivitiesFrom' => '2025-06-10', 'addActivitiesTill' => ''],
            'Fill in both dates to add the activities in a date range.',
        ];

        yield 'date range with an invalid date' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'addActivitiesFrom' => '2025-02-30', 'addActivitiesTill' => '2025-03-02'],
            'Fill in both dates to add the activities in a date range.',
        ];

        yield 'date range ending before it starts' => [
            ['activityCollectionId' => 'activityCollection-1', 'name' => 'Mallorca', 'type' => 'trip', 'addActivitiesFrom' => '2025-06-12', 'addActivitiesTill' => '2025-06-10'],
            'The start date must be on or before the end date.',
        ];
    }
}
