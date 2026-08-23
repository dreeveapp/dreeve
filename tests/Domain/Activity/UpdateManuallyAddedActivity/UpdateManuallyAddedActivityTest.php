<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\UpdateManuallyAddedActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\UpdateManuallyAddedActivity\UpdateManuallyAddedActivity;
use App\Domain\Activity\WorkoutType;
use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateManuallyAddedActivityTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => '  My manual activity  ',
            'description' => 'A nice run',
            'sportType' => 'Run',
            'workoutType' => 'race',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['hours' => '1', 'minutes' => '2', 'seconds' => '3'],
            'distance' => '10.5',
            'elevation' => '120',
            'calories' => '750',
            'gearId' => 'gear-1',
            'isCommute' => 'true',
        ]);

        $this->assertEquals(ActivityId::fromUnprefixed('1'), $command->getActivityId());
        $this->assertEquals(ActivityName::fromString('My manual activity'), $command->getName());
        $this->assertSame('A nice run', $command->getDescription());
        $this->assertSame(SportType::RUN, $command->getSportType());
        $this->assertSame(WorkoutType::RACE, $command->getWorkoutType());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:00'), $command->getStartDateTime());
        $this->assertSame(3723, $command->getDurationInSeconds());
        $this->assertSame(10.5, $command->getDistance());
        $this->assertSame(120.0, $command->getElevation());
        $this->assertEquals(GearId::fromUnprefixed('1'), $command->getGearId());
        $this->assertSame(750, $command->getCalories());
        $this->assertTrue($command->isCommute());
        $this->assertSame([], $command->getNewImages());
        $this->assertSame([], $command->getRemovedImages());
    }

    public function testFromPayloadWithEmptyOptionalFields(): void
    {
        $command = UpdateManuallyAddedActivity::fromPayload([
            ...self::validPayload(),
            'description' => '   ',
            'gearId' => '',
            'elevation' => '',
            'calories' => '',
            'workoutType' => '',
        ]);

        $this->assertNull($command->getDescription());
        $this->assertNull($command->getGearId());
        $this->assertNull($command->getCalories());
        $this->assertNull($command->getWorkoutType());
        $this->assertSame(0.0, $command->getElevation());
        $this->assertFalse($command->isCommute());
    }

    public function testFromPayloadParsesNewAndRemovedImages(): void
    {
        $command = UpdateManuallyAddedActivity::fromPayload([
            ...self::validPayload(),
            'images' => json_encode([
                ['status' => 'new', 'filename' => 'photo.jpg', 'content' => base64_encode('binary-content')],
                ['status' => 'removed', 'path' => '/files/activities/existing.png'],
            ]),
        ]);

        $newImages = $command->getNewImages();
        $this->assertCount(1, $newImages);
        $this->assertSame('jpg', $newImages[0]->getFilename()->getExtension());
        $this->assertSame('binary-content', $newImages[0]->getContent());

        $removedImages = $command->getRemovedImages();
        $this->assertCount(1, $removedImages);
        $this->assertSame('files/activities/existing.png', $removedImages[0]->getPath()->toLocalImagePath());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateManuallyAddedActivity::fromPayload([...self::validPayload(), ...$payload]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing activityId' => [['activityId' => null], 'An "activityId" is required.'];
        yield 'empty name' => [['name' => '   '], 'The name cannot be empty.'];
        yield 'invalid sportType' => [['sportType' => 'NotARealSport'], 'A valid "sportType" is required.'];
        yield 'invalid workoutType' => [['workoutType' => 'NotARealWorkout'], 'The "workoutType" is invalid.'];
        yield 'malformed startDateTime' => [['startDateTime' => '17-10-2023 16:15'], 'The "startDateTime" is invalid.'];
        yield 'zero duration' => [['duration' => ['hours' => 0, 'minutes' => 0, 'seconds' => 0]], 'The duration must be greater than zero.'];
        yield 'negative distance' => [['distance' => '-1'], 'The "distance" must be a positive number.'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
    {
        return [
            'activityId' => 'activity-1',
            'name' => 'My manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['hours' => 0, 'minutes' => 50, 'seconds' => 0],
            'distance' => '10',
            'elevation' => '120',
        ];
    }
}
