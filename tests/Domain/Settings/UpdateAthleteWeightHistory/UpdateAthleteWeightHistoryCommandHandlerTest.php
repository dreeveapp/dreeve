<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteWeightHistory;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateAthleteWeightHistory\UpdateAthleteWeightHistory;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Tests\ContainerTestCase;

class UpdateAthleteWeightHistoryCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    // Uncached on purpose: the handler writes through this same repository,
    // so a memoized find() would hide what was actually persisted.
    private KeyValueBasedSettingsRepository $settingsRepository;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(UpdateAthleteWeightHistory::fromPayload([
            'entries' => [
                ['on' => '2024-01-01', 'weight' => 70.5],
                ['on' => '2024-06-01', 'weight' => 68],
            ],
        ]));

        // assertEquals, not assertSame: a whole-number float round-trips through
        // JSON as an int, so 68.0 comes back as 68.
        $this->assertEquals(
            [
                ['on' => '2024-01-01', 'weight' => 70.5],
                ['on' => '2024-06-01', 'weight' => 68.0],
            ],
            $this->athlete()['weightHistory']
        );
    }

    public function testItReplacesRatherThanMergesTheHistory(): void
    {
        $this->commandBus->dispatch(UpdateAthleteWeightHistory::fromPayload([
            'entries' => [['on' => '2024-01-01', 'weight' => 70.5]],
        ]));

        $this->assertCount(1, $this->athlete()['weightHistory']);
    }

    public function testItPreservesUnrelatedAthleteSettings(): void
    {
        $this->commandBus->dispatch(UpdateAthleteWeightHistory::fromPayload([
            'entries' => [['on' => '2024-01-01', 'weight' => 70.5]],
        ]));

        $athlete = $this->athlete();
        $this->assertSame('1989-08-14', $athlete['birthday']);
        $this->assertSame('fox', $athlete['maxHeartRateFormula']);
        $this->assertCount(4, $athlete['ftpHistory']['cycling']);
    }

    public function testItAcceptsAnEmptyHistory(): void
    {
        $this->commandBus->dispatch(UpdateAthleteWeightHistory::fromPayload(['entries' => []]));

        $this->assertSame([], $this->athlete()['weightHistory']);
    }

    public function testItThrowsOnMissingEntries(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"entries" must be an array.');

        UpdateAthleteWeightHistory::fromPayload([]);
    }

    public function testItThrowsOnANonObjectEntry(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 must be an object.');

        UpdateAthleteWeightHistory::fromPayload(['entries' => ['nope']]);
    }

    public function testItThrowsOnAMissingDate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 is missing a valid "on" date.');

        UpdateAthleteWeightHistory::fromPayload(['entries' => [['weight' => 70]]]);
    }

    public function testItThrowsOnANonNumericWeight(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 is missing a valid numeric "weight".');

        UpdateAthleteWeightHistory::fromPayload(['entries' => [['on' => '2024-01-01', 'weight' => 'heavy']]]);
    }

    public function testItThrowsOnAnInvalidDate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        UpdateAthleteWeightHistory::fromPayload(['entries' => [['on' => 'not-a-date', 'weight' => 70]]]);
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
