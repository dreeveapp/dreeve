<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteRestingHeartRate;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateAthleteRestingHeartRate\UpdateAthleteRestingHeartRate;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Tests\ContainerTestCase;

class UpdateAthleteRestingHeartRateCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueBasedSettingsRepository $settingsRepository;

    public function testHandleForTheAgeBasedHeuristic(): void
    {
        $this->commandBus->dispatch(UpdateAthleteRestingHeartRate::fromPayload([
            'type' => 'formula',
            'formula' => 'heuristicAgeBased',
        ]));

        $this->assertSame('heuristicAgeBased', $this->athlete()['restingHeartRateFormula']);
    }

    public function testHandleForAFixedValue(): void
    {
        $this->commandBus->dispatch(UpdateAthleteRestingHeartRate::fromPayload([
            'type' => 'fixed',
            'bpm' => 52,
        ]));

        $this->assertSame(52, $this->athlete()['restingHeartRateFormula']);
    }

    public function testHandleForMeasuredValues(): void
    {
        $this->commandBus->dispatch(UpdateAthleteRestingHeartRate::fromPayload([
            'type' => 'measured',
            'entries' => [
                ['on' => '2024-01-01', 'bpm' => 54],
                ['on' => '2025-01-01', 'bpm' => 50],
            ],
        ]));

        $this->assertSame(
            ['2024-01-01' => 54, '2025-01-01' => 50],
            $this->athlete()['restingHeartRateFormula']
        );
    }

    public function testItAcceptsANumericStringAsFixed(): void
    {
        $this->commandBus->dispatch(UpdateAthleteRestingHeartRate::fromPayload([
            'type' => 'fixed',
            'bpm' => '48',
        ]));

        $this->assertSame(48, $this->athlete()['restingHeartRateFormula']);
    }

    public function testItPreservesUnrelatedAthleteSettings(): void
    {
        $this->commandBus->dispatch(UpdateAthleteRestingHeartRate::fromPayload([
            'type' => 'fixed',
            'bpm' => 52,
        ]));

        $athlete = $this->athlete();
        $this->assertSame('1989-08-14', $athlete['birthday']);
        $this->assertSame('fox', $athlete['maxHeartRateFormula']);
    }

    public function testItThrowsOnAnUnknownFormula(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Invalid RESTING_HEART_RATE_FORMULA "guesswork" detected');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'formula', 'formula' => 'guesswork']);
    }

    public function testItThrowsOnAnUnknownType(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "type" is required, one of: formula, fixed, measured.');

        UpdateAthleteRestingHeartRate::fromPayload([]);
    }

    public function testItThrowsOnANonPositiveFixedValue(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('needs a heart rate greater than zero');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'fixed', 'bpm' => 0]);
    }

    public function testItThrowsOnAFractionalFixedValue(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('needs a heart rate greater than zero');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'fixed', 'bpm' => 52.4]);
    }

    public function testItThrowsOnAMissingFormula(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"formula" is required when type is "formula".');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'formula']);
    }

    public function testItThrowsOnEmptyMeasuredValues(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A non-empty "entries" array is required when type is "measured".');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'measured', 'entries' => []]);
    }

    public function testItThrowsOnAMeasuredEntryWithoutADate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('needs a date');

        UpdateAthleteRestingHeartRate::fromPayload(['type' => 'measured', 'entries' => [['bpm' => 52]]]);
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
