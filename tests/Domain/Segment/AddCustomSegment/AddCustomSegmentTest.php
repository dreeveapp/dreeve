<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\AddCustomSegment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\AddCustomSegment\AddCustomSegment;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddCustomSegmentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddCustomSegment::fromPayload([
            'activityId' => 'activity-1',
            'startIndex' => '10',
            'endIndex' => 120,
            'name' => '  Kwaremont  ',
            'isFavourite' => true,
        ]);

        $this->assertEquals(ActivityId::fromUnprefixed('1'), $command->getActivityId());
        $this->assertSame(10, $command->getStartIndex());
        $this->assertSame(120, $command->getEndIndex());
        $this->assertEquals(Name::fromString('Kwaremont'), $command->getName());
        $this->assertTrue($command->isFavourite());
    }

    public function testFromPayloadDefaultsToNotFavourite(): void
    {
        $command = AddCustomSegment::fromPayload([
            'activityId' => 'activity-1',
            'startIndex' => 0,
            'endIndex' => 1,
            'name' => 'Kwaremont',
        ]);

        $this->assertFalse($command->isFavourite());
    }

    #[DataProvider(methodName: 'provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedReason): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedReason));

        AddCustomSegment::fromPayload($payload);
    }

    public static function provideInvalidPayloads(): iterable
    {
        $valid = ['activityId' => 'activity-1', 'startIndex' => 0, 'endIndex' => 10, 'name' => 'Kwaremont'];

        yield 'missing activity' => [array_diff_key($valid, ['activityId' => true]), 'An "activityId" is required.'];
        yield 'missing name' => [array_diff_key($valid, ['name' => true]), 'A "name" is required.'];
        yield 'blank name' => [[...$valid, 'name' => '   '], 'A "name" is required.'];
        yield 'missing start' => [array_diff_key($valid, ['startIndex' => true]), 'The "startIndex" must be a positive whole number.'];
        yield 'negative start' => [[...$valid, 'startIndex' => -1], 'The "startIndex" must be a positive whole number.'];
        yield 'decimal end' => [[...$valid, 'endIndex' => '1.5'], 'The "endIndex" must be a positive whole number.'];
        yield 'end before start' => [[...$valid, 'startIndex' => 10, 'endIndex' => 5], 'The end of the segment must come after its start.'];
        yield 'end equals start' => [[...$valid, 'startIndex' => 10, 'endIndex' => 10], 'The end of the segment must come after its start.'];
    }
}
