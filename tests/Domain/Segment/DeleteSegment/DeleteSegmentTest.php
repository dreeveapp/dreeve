<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\DeleteSegment;

use App\Domain\Segment\DeleteSegment\DeleteSegment;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DeleteSegmentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteSegment::fromPayload([
            'segmentId' => 'segment-1',
        ]);

        $this->assertEquals(SegmentId::fromUnprefixed('1'), $command->getSegmentId());
    }

    #[TestWith([[]])]
    #[TestWith([['segmentId' => 1]])]
    #[TestWith([['segmentId' => '  ']])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "segmentId" is required.'));

        DeleteSegment::fromPayload($payload);
    }
}
