<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteFtpHistory;

use App\Domain\Ftp\FtpSport;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateAthleteFtpHistory\UpdateAthleteFtpHistory;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class UpdateAthleteFtpHistoryCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    // Uncached on purpose: the handler writes through this same repository,
    // so a memoized find() would hide what was actually persisted.
    private KeyValueBasedSettingsRepository $settingsRepository;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [['on' => '2025-01-01', 'ftp' => 300]],
        ]));

        $this->assertSame(
            [['on' => '2025-01-01', 'ftp' => 300]],
            $this->athlete()['ftpHistory'][FtpSport::CYCLING->value]
        );
    }

    public function testItLeavesTheOtherSportUntouched(): void
    {
        $before = $this->athlete()['ftpHistory'][FtpSport::RUNNING->value];

        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [['on' => '2025-01-01', 'ftp' => 300]],
        ]));

        $this->assertSame($before, $this->athlete()['ftpHistory'][FtpSport::RUNNING->value]);
    }

    public function testItMigratesALegacyUnkeyedHistoryToCycling(): void
    {
        $this->provideLegacyFtpHistory([
            ['on' => '2020-01-01', 'ftp' => 200],
            ['on' => '2021-01-01', 'ftp' => 210],
        ]);

        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'running',
            'entries' => [['on' => '2025-01-01', 'ftp' => 150]],
        ]));

        $ftpHistory = $this->athlete()['ftpHistory'];
        // The legacy entries were cycling by convention, so they must survive
        // under an explicit key rather than being dropped on the next read.
        $this->assertSame(
            [['on' => '2020-01-01', 'ftp' => 200], ['on' => '2021-01-01', 'ftp' => 210]],
            $ftpHistory[FtpSport::CYCLING->value]
        );
        $this->assertSame([['on' => '2025-01-01', 'ftp' => 150]], $ftpHistory[FtpSport::RUNNING->value]);
    }

    public function testItPreservesUnrelatedAthleteSettings(): void
    {
        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'running',
            'entries' => [['on' => '2025-01-01', 'ftp' => 150]],
        ]));

        $athlete = $this->athlete();
        $this->assertSame('1989-08-14', $athlete['birthday']);
        $this->assertCount(4, $athlete['weightHistory']);
    }

    public function testItAcceptsAnEmptyHistory(): void
    {
        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [],
        ]));

        $this->assertSame([], $this->athlete()['ftpHistory'][FtpSport::CYCLING->value]);
    }

    public function testItThrowsOnAnUnknownSport(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "sport" is required, one of: cycling, running.');

        UpdateAthleteFtpHistory::fromPayload(['sport' => 'swimming', 'entries' => []]);
    }

    public function testItThrowsOnAMissingSport(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        UpdateAthleteFtpHistory::fromPayload(['entries' => []]);
    }

    public function testItThrowsOnANonNumericFtp(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 is missing a valid numeric "ftp".');

        UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [['on' => '2025-01-01', 'ftp' => 'strong']],
        ]);
    }

    public function testItThrowsOnAnFtpBelowOne(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Minimum FTP of 1 expected');

        UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [['on' => '2025-01-01', 'ftp' => 0]],
        ]);
    }

    public function testItThrowsWhenEntriesIsNotAnArray(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"entries" must be an array.');

        UpdateAthleteFtpHistory::fromPayload(['sport' => 'cycling', 'entries' => 'nope']);
    }

    public function testItThrowsOnANonObjectEntry(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 must be an object.');

        UpdateAthleteFtpHistory::fromPayload(['sport' => 'cycling', 'entries' => ['nope']]);
    }

    public function testItHandlesAnAbsentFtpHistory(): void
    {
        // No ftpHistory key at all: the legacy migration must not invent a
        // bogus cycling entry out of an empty array.
        $this->provideAthleteWithoutFtpHistory();

        $this->commandBus->dispatch(UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'running',
            'entries' => [['on' => '2025-01-01', 'ftp' => 150]],
        ]));

        $this->assertSame(
            [FtpSport::RUNNING->value => [['on' => '2025-01-01', 'ftp' => 150]]],
            $this->athlete()['ftpHistory']
        );
    }

    public function testItThrowsOnAMissingDate(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Entry #0 is missing a valid "on" date.');

        UpdateAthleteFtpHistory::fromPayload([
            'sport' => 'cycling',
            'entries' => [['ftp' => 250]],
        ]);
    }

    private function provideAthleteWithoutFtpHistory(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        unset($general['athlete']['ftpHistory']);

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::GENERAL->keyValueKey(),
            Value::fromString(Json::encode($general)),
        ));
    }

    /**
     * @param list<array{on: string, ftp: int}> $entries
     */
    private function provideLegacyFtpHistory(array $entries): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        $general['athlete']['ftpHistory'] = $entries;

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::GENERAL->keyValueKey(),
            Value::fromString(Json::encode($general)),
        ));
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
