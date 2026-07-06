<?php

namespace App\Tests\Application\Build\RunBuild;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Application\Build\RunBuild\RunBuild;
use App\Application\Build\RunBuild\RunBuildCommandHandler;
use App\Application\Import\StravaImport\ImportGear\GearImportStatus;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\SpyOutput;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunBuildCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private RunBuildCommandHandler $buildAppCommandHandler;
    private CommandBus $commandBus;
    private MockObject $logger;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withGearId(GearId::fromUnprefixed(4))
                ->build(), []
        ));
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(4))
                ->build()
        );

        $output = new SpyOutput();
        $this->buildAppCommandHandler->handle(new RunBuild(
            output: new SymfonyStyle(new StringInput('input'), $output),
        ));
        $this->assertMatchesTextSnapshot(str_replace(' ', '', $output));
        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testHandleWhenNotAllGearHasBeenImportedYet(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withGearId(GearId::fromUnprefixed(4))
                ->build(),
            [
                'gear_id' => '4',
            ]
        ));

        $output = new SpyOutput();
        $this->buildAppCommandHandler->handle(new RunBuild(
            output: new SymfonyStyle(new StringInput('input'), $output),
        ));
        $this->assertStringContainsString('[WARNING] Some of your gear hasn’t been imported yet', $output);
    }

    public function testHandleWhenNoActivitiesHaveBeenImported(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseNoActivitiesHaveBeenImportedYet());
        $this->buildAppCommandHandler->handle(new RunBuild(
            output: new SymfonyStyle(new StringInput('input'), new SpyOutput()),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildAppCommandHandler = new RunBuildCommandHandler(
            commandBus: $this->commandBus = new SpyCommandBus(),
            appStatusChecker: $this->getContainer()->get(AppStatusChecker::class),
            gearImportStatus: $this->getContainer()->get(GearImportStatus::class),
            clock: PausedClock::on(SerializableDateTime::fromString('2023-10-17 16:15:04')),
        );
    }
}
