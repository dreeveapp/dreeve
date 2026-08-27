<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityGearUsage;
use App\Domain\Activity\Shifting\ActivityGearUsages;
use App\Domain\Activity\Shifting\GearPosition;
use App\Domain\Import\FileParser\Fit\FitGearUsageExtractor;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class FitGearUsageExtractorTest extends TestCase
{
    private const int START_FIT_SECONDS = 1000000000;

    public function testExtract(): void
    {
        $this->assertSame(
            [
                'front 1 (39T) 4s 1x',
                'front 2 (53T) 4s 0x',
                'rear 4 (19T) 2s 0x',
                'rear 5 (17T) 0s 1x',
                'rear 6 (16T) 2s 1x',
                'rear 7 (15T) 1s 1x',
                'rear 8 (14T) 3s 1x',
            ],
            $this->describe($this->extract(range(0, 8)))
        );
    }

    public function testExtractIgnoresSamplesRecordedBeforeTheFirstShift(): void
    {
        $this->assertSame(
            8,
            $this->extract(range(0, 8))
                ->filterOnPosition(GearPosition::REAR)
                ->sum(fn (ActivityGearUsage $gearUsage): int => $gearUsage->getTimeInSeconds())
        );
    }

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
            $this->describe($this->extract([0, 1, 5]))
        );
    }

    public function testExtractWithoutGearChanges(): void
    {
        $this->assertTrue($this->extract(range(0, 8), 'fit-document.json')->isEmpty());
    }

    public function testExtractWithoutEventMessages(): void
    {
        $this->assertEquals(
            ActivityGearUsages::empty(),
            FitGearUsageExtractor::extract([], self::START_FIT_SECONDS, range(0, 8), ActivityId::fromUnprefixed('test'))
        );
    }

    /**
     * @param array<int, mixed> $timeStream
     */
    private function extract(array $timeStream, string $fixture = 'fit-document-with-gear-changes.json'): ActivityGearUsages
    {
        return FitGearUsageExtractor::extract(
            $this->eventMessagesFromFixture($fixture),
            self::START_FIT_SECONDS,
            $timeStream,
            ActivityId::fromUnprefixed('test'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventMessagesFromFixture(string $fixture): array
    {
        $eventMessages = [];
        foreach (Json::decodeLazy(json: (string) file_get_contents(__DIR__.'/../fixtures/'.$fixture), pointer: '/files/-/messages') as $message) {
            if ('event' !== $message['name']) {
                continue;
            }

            $fields = [];
            foreach ($message['fields'] as $field) {
                $fields[$field['name']] = $field['value'];
            }
            $eventMessages[] = $fields;
        }

        return $eventMessages;
    }

    /**
     * @return list<string>
     */
    private function describe(ActivityGearUsages $gearUsages): array
    {
        return $gearUsages->map(fn (ActivityGearUsage $gearUsage): string => sprintf(
            '%s %d (%dT) %ds %dx',
            $gearUsage->getPosition()->value,
            $gearUsage->getGearNumber(),
            $gearUsage->getTeeth(),
            $gearUsage->getTimeInSeconds(),
            $gearUsage->getShiftCount(),
        ));
    }
}
