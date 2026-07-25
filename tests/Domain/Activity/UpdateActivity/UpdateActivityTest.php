<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\UpdateActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\UpdateActivity\UpdateActivity;
use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateActivityTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Run',
            'description' => 'A nice run',
            'deviceName' => 'Garmin Edge',
            'gearId' => 'gear-1',
            'isCommute' => 'true',
        ]);

        $this->assertEquals(ActivityId::fromUnprefixed('1'), $command->getActivityId());
        $this->assertEquals(ActivityName::fromString('My custom activity'), $command->getName());
        $this->assertSame(SportType::RUN, $command->getSportType());
        $this->assertSame('A nice run', $command->getDescription());
        $this->assertSame('Garmin Edge', $command->getDeviceName());
        $this->assertEquals(GearId::fromUnprefixed('1'), $command->getGearId());
        $this->assertTrue($command->isCommute());
    }

    public function testFromPayloadWithoutImagesKeyLeavesImagesUntouched(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Ride',
        ]);

        $this->assertSame([], $command->getNewImages());
        $this->assertSame([], $command->getRemovedImages());
    }

    public function testFromPayloadWithEmptyImages(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Ride',
            'images' => '[]',
        ]);

        $this->assertSame([], $command->getNewImages());
        $this->assertSame([], $command->getRemovedImages());
    }

    public function testFromPayloadParsesNewAndRemovedImagesAndIgnoresUnchanged(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Ride',
            'images' => json_encode([
                ['status' => 'new', 'filename' => 'photo.jpg', 'content' => base64_encode('binary-content')],
                ['status' => 'unchanged', 'path' => '/files/activities/existing.png'],
                ['status' => 'removed', 'path' => '/files/activities/gone.png'],
            ]),
        ]);

        $newImages = $command->getNewImages();
        $this->assertCount(1, $newImages);
        $this->assertSame('jpg', $newImages[0]->getFilename()->getExtension());
        $this->assertSame('binary-content', $newImages[0]->getContent());

        $removedImages = $command->getRemovedImages();
        $this->assertCount(1, $removedImages);
        $this->assertSame('files/activities/gone.png', $removedImages[0]->getPath()->toLocalImagePath());
    }

    #[DataProvider('provideInvalidImagePayloads')]
    public function testFromPayloadThrowsOnInvalidImagePayload(mixed $images, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Ride',
            'images' => $images,
        ]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideInvalidImagePayloads(): iterable
    {
        yield 'new image with an unsupported file type' => [
            json_encode([
                ['status' => 'new', 'filename' => 'malware.php', 'content' => base64_encode('binary-content')],
            ]),
            'Unsupported image file type.',
        ];

        yield 'new image with invalid base64 content' => [
            json_encode([
                ['status' => 'new', 'filename' => 'photo.jpg', 'content' => 'not-valid-base64!!!'],
            ]),
            'A new image has invalid "content".',
        ];

        yield 'image with an unknown status' => [
            json_encode([
                ['status' => 'whatever'],
            ]),
            'Each image requires a valid "status".',
        ];

        yield 'images is not a string' => [
            ['not', 'a', 'string'],
            'The "images" field is invalid.',
        ];

        yield 'images does not decode to an array' => [
            '123',
            'The "images" field is invalid.',
        ];

        yield 'new image missing filename and content' => [
            json_encode([
                ['status' => 'new'],
            ]),
            'A new image requires a "filename" and "content".',
        ];

        yield 'removed image missing path' => [
            json_encode([
                ['status' => 'removed'],
            ]),
            'A removed image requires a "path".',
        ];
    }

    public function testFromPayloadWithEmptyOptionalFields(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My custom activity',
            'sportType' => 'Ride',
            'description' => '   ',
            'deviceName' => '',
            'gearId' => '',
        ]);

        $this->assertNull($command->getDescription());
        $this->assertNull($command->getDeviceName());
        $this->assertNull($command->getGearId());
        $this->assertFalse($command->isCommute());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidScalarFieldPayloads')]
    public function testFromPayloadThrowsOnInvalidScalarFieldPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateActivity::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidScalarFieldPayloads(): iterable
    {
        yield 'missing sportType' => [
            ['activityId' => 'activity-1', 'name' => 'My custom activity'],
            'A valid "sportType" is required.',
        ];

        yield 'invalid sportType' => [
            ['activityId' => 'activity-1', 'name' => 'My custom activity', 'sportType' => 'NotARealSport'],
            'A valid "sportType" is required.',
        ];

        yield 'missing activityId' => [
            ['name' => 'My custom activity'],
            'An "activityId" and "name" are required.',
        ];

        yield 'missing name' => [
            ['activityId' => 'activity-1'],
            'An "activityId" and "name" are required.',
        ];

        yield 'empty name' => [
            ['activityId' => 'activity-1', 'name' => '   ', 'sportType' => 'Ride'],
            'The name cannot be empty.',
        ];
    }

    public function testFromPayloadTrimsName(): void
    {
        $command = UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => '  My custom activity  ',
            'sportType' => 'Ride',
        ]);

        $this->assertEquals(ActivityName::fromString('My custom activity'), $command->getName());
    }
}
