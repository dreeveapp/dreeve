<?php

namespace App\Tests\Application\Import\StravaImport\ImportSegments;

use App\Application\Import\StravaImport\ImportSegments\ImportSegments;
use App\Application\Import\StravaImport\ImportSegments\ImportSegmentsCommandHandler;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class ImportSegmentsCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CommandBus $commandBus;
    private SpyStrava $strava;

    public function testHandle(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(2);

        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withName(Name::fromString('⭐️ Segment'))
                ->withIsFavourite(false)
                ->build()
        );
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('2'))
                ->withName(Name::fromString('Segment One'))
                ->withIsFavourite(false)
                ->build()
        );

        $commandHandler = new ImportSegmentsCommandHandler(
            $this->getContainer()->get(SegmentRepository::class),
            $this->seedOptInToSegmentDetailsImport(true),
            $this->strava,
            new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-12-04'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            )
        );

        $commandHandler->handle(new ImportSegments($output));
        $this->assertMatchesTextSnapshot($output);
    }

    public function testHandleWhenExceptionIsThrown(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->triggerExceptionOnNextCall();

        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withName(Name::fromString('⭐️ Segment'))
                ->withIsFavourite(false)
                ->build()
        );

        $commandHandler = new ImportSegmentsCommandHandler(
            $this->getContainer()->get(SegmentRepository::class),
            $this->seedOptInToSegmentDetailsImport(true),
            $this->strava,
            new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-12-04'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            )
        );

        $commandHandler->handle(new ImportSegments($output));
        $this->assertMatchesTextSnapshot($output);
    }

    public function testHandleWhenSegmentDetailsAreDisabled(): void
    {
        $commandHandler = new ImportSegmentsCommandHandler(
            $this->getContainer()->get(SegmentRepository::class),
            $this->seedOptInToSegmentDetailsImport(false),
            $this->strava,
            new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-12-04'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            )
        );

        $output = new SpyOutput();
        $commandHandler->handle(new ImportSegments($output));

        $this->assertEmpty((string) $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->strava = $this->getContainer()->get(Strava::class);

        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importDataOrBuildApp', 'value' => '{"lockAcquiredBy": "test"}']
        );
    }

    private function seedOptInToSegmentDetailsImport(bool $optIn): SettingsRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::IMPORT, [
            'optInToSegmentDetailImport' => $optIn,
        ]);

        return $settingsRepository;
    }
}
