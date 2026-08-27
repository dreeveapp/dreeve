<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Activity\Shifting\GearShift;
use App\Domain\Activity\Shifting\GearShifts;
use App\Domain\Import\FileParser\Fit\FitGearShiftExtractor;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class FitGearShiftExtractorTest extends TestCase
{
    public function testExtract(): void
    {
        $this->assertEquals(
            GearShifts::fromArray([
                // The front derailleur had not reported yet, its position is taken from the next shift.
                GearShift::create(timeOffsetInSeconds: 0, frontGearNumber: 2, frontGearTeeth: 53, rearGearNumber: 4, rearGearTeeth: 19),
                GearShift::create(timeOffsetInSeconds: 1, frontGearNumber: 2, frontGearTeeth: 53, rearGearNumber: 4, rearGearTeeth: 19),
                GearShift::create(timeOffsetInSeconds: 3, frontGearNumber: 2, frontGearTeeth: 53, rearGearNumber: 5, rearGearTeeth: 17),
                GearShift::create(timeOffsetInSeconds: 3, frontGearNumber: 2, frontGearTeeth: 53, rearGearNumber: 6, rearGearTeeth: 16),
                GearShift::create(timeOffsetInSeconds: 5, frontGearNumber: 1, frontGearTeeth: 39, rearGearNumber: 7, rearGearTeeth: 15),
                GearShift::create(timeOffsetInSeconds: 6, frontGearNumber: 1, frontGearTeeth: 39, rearGearNumber: 8, rearGearTeeth: 14),
            ]),
            FitGearShiftExtractor::extract($this->messagesFromFixture('fit-document-with-gear-changes.json'))
        );
    }

    public function testExtractWithoutGearChanges(): void
    {
        $this->assertEquals(
            GearShifts::empty(),
            FitGearShiftExtractor::extract($this->messagesFromFixture('fit-document.json'))
        );
    }

    public function testExtractWithoutMessages(): void
    {
        $this->assertEquals(
            GearShifts::empty(),
            FitGearShiftExtractor::extract([])
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function messagesFromFixture(string $fixture): iterable
    {
        return Json::decodeLazy(
            json: (string) file_get_contents(__DIR__.'/../fixtures/'.$fixture),
            pointer: '/files/-/messages',
        );
    }
}
