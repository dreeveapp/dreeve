<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteMaxHeartRate;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateAthleteMaxHeartRate\UpdateAthleteMaxHeartRate;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Tests\ContainerTestCase;

class UpdateAthleteMaxHeartRateCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueBasedSettingsRepository $settingsRepository;

    public function testHandleForANamedFormula(): void
    {
        $this->commandBus->dispatch(UpdateAthleteMaxHeartRate::fromPayload([
            'type' => 'formula',
            'formula' => 'tanaka',
        ]));

        $this->assertSame('tanaka', $this->athlete()['maxHeartRateFormula']);
    }

    public function testHandleForMeasuredValues(): void
    {
        $this->commandBus->dispatch(UpdateAthleteMaxHeartRate::fromPayload([
            'type' => 'measured',
            'entries' => [
                ['on' => '2020-01-01', 'bpm' => 198],
                ['on' => '2025-01-10', 'bpm' => 193],
            ],
        ]));

        // Persisted in the app's date => bpm shape, which is what
        // MaxHeartRateFormulas expects.
        $this->assertSame(
            ['2020-01-01' => 198, '2025-01-10' => 193],
            $this->athlete()['maxHeartRateFormula']
        );
    }

    public function testItPreservesUnrelatedAthleteSettings(): void
    {
        $this->commandBus->dispatch(UpdateAthleteMaxHeartRate::fromPayload([
            'type' => 'formula',
            'formula' => 'nes',
        ]));

        $athlete = $this->athlete();
        $this->assertSame('1989-08-14', $athlete['birthday']);
        $this->assertCount(4, $athlete['weightHistory']);
    }

    public function testItThrowsOnAnUnknownFormula(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Invalid MAX_HEART_RATE_FORMULA "guesswork" detected');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'formula', 'formula' => 'guesswork']);
    }

    public function testItThrowsOnAnUnknownType(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "type" is required, one of: formula, measured.');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'vibes']);
    }

    public function testItThrowsOnAMissingFormula(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"formula" is required when type is "formula".');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'formula']);
    }

    public function testItThrowsOnEmptyMeasuredValues(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A non-empty "entries" array is required when type is "measured".');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'measured', 'entries' => []]);
    }

    public function testItThrowsOnADuplicateDate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('cannot contain the same date more than once');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'measured', 'entries' => [
            ['on' => '2020-01-01', 'bpm' => 198],
            ['on' => '2020-01-01', 'bpm' => 190],
        ]]);
    }

    public function testItThrowsOnANonPositiveHeartRate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('needs a heart rate greater than zero');

        UpdateAthleteMaxHeartRate::fromPayload(['type' => 'measured', 'entries' => [
            ['on' => '2020-01-01', 'bpm' => 0],
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function athlete(): array
    {
        return $this->settingsRepository->find(SettingsGroup::GENERAL)['athlete'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);
    }
}
