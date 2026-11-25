<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsages;
use App\Domain\Import\FileParser\Fit\FitDrivetrainUsageExtractor;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class FitDrivetrainUsageExtractorTest extends TestCase
{
    private const int START_FIT_SECONDS = 1000000000;

    public function testExtractIgnoresStoppedTime(): void
    {
        $this->assertSame(
            [
                'front 1 (39T) 1s 1x',
                'front 2 (53T) 1s 0x',
                'rear 4 (19T) 1s 0x',
                'rear 5 (17T) 0s 1x',
                'rear 6 (16T) 0s 1x',
                'rear 7 (15T) 1s 1x',
                'rear 8 (14T) 0s 1x',
            ],
            $this->describe(FitDrivetrainUsageExtractor::extract(
                $this->eventMessagesFromFixture(),
                self::START_FIT_SECONDS,
                [0, 1, 5],
                ActivityId::fromUnprefixed('test'),
            ))
        );
    }

    public function testExtractWithoutEventMessages(): void
    {
        $this->assertEquals(
            ActivityDrivetrainUsages::empty(),
            FitDrivetrainUsageExtractor::extract([], self::START_FIT_SECONDS, range(0, 8), ActivityId::fromUnprefixed('test'))
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventMessagesFromFixture(): array
    {
        $eventMessages = [];
        foreach (array_filter(explode("\n", (string) file_get_contents(__DIR__.'/../fixtures/fit-document-with-gear-changes.json'))) as $line) {
            $message = Json::decode($line);
            if ('event' !== $message['name']) {
                continue;
            }
            $eventMessages[] = $message['fields'];
        }

        return $eventMessages;
    }

    /**
     * @return array<mixed>
     */
    private function describe(ActivityDrivetrainUsages $drivetrainUsages): array
    {
        return $drivetrainUsages->map(fn (ActivityDrivetrainUsage $drivetrainUsage): string => sprintf(
            '%s %d (%dT) %ds %dx',
            $drivetrainUsage->getPosition()->value,
            $drivetrainUsage->getGearNumber(),
            $drivetrainUsage->getTeeth(),
            $drivetrainUsage->getTimeInSeconds(),
            $drivetrainUsage->getShiftCount(),
        ));
    }
}
