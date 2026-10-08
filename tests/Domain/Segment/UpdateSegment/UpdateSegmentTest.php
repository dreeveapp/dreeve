<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\UpdateSegment;

use App\Domain\Segment\SegmentId;
use App\Domain\Segment\UpdateSegment\UpdateSegment;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateSegmentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateSegment::fromPayload([
            'segmentId' => 'segment-1',
            'name' => '  Kwaremont  ',
            'isFavourite' => 'true',
        ]);

        $this->assertEquals(SegmentId::fromUnprefixed('1'), $command->getSegmentId());
        $this->assertEquals(Name::fromString('Kwaremont'), $command->getName());
        $this->assertTrue($command->isFavourite());
    }

    public function testFromPayloadDefaultsToNotFavourite(): void
    {
        $command = UpdateSegment::fromPayload([
            'segmentId' => 'segment-1',
            'name' => 'Kwaremont',
        ]);

        $this->assertFalse($command->isFavourite());
    }

    #[DataProvider(methodName: 'provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedReason): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedReason));

        UpdateSegment::fromPayload($payload);
    }

    public static function provideInvalidPayloads(): iterable
    {
        $valid = ['segmentId' => 'segment-1', 'name' => 'Kwaremont'];

        yield 'missing segment' => [array_diff_key($valid, ['segmentId' => true]), 'A "segmentId" is required.'];
        yield 'missing name' => [array_diff_key($valid, ['name' => true]), 'A "name" is required.'];
        yield 'blank name' => [[...$valid, 'name' => '  '], 'A "name" is required.'];
    }
}
