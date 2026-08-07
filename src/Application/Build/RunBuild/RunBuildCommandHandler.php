<?php

declare(strict_types=1);

namespace App\Application\Build\RunBuild;

use App\Application\AppStatusChecker;
use App\Application\Build\BuildActivitiesHtml\BuildActivitiesHtml;
use App\Application\Build\BuildBadgeSvg\BuildBadgeSvg;
use App\Application\Build\BuildBestEffortsHtml\BuildBestEffortsHtml;
use App\Application\Build\BuildDashboardHtml\BuildDashboardHtml;
use App\Application\Build\BuildGearMaintenanceHtml\BuildGearMaintenanceHtml;
use App\Application\Build\BuildGearStatsHtml\BuildGearStatsHtml;
use App\Application\Build\BuildManifest\BuildManifest;
use App\Application\Build\BuildMonthlyStatsHtml\BuildMonthlyStatsHtml;
use App\Application\Build\BuildRecordingDevices\BuildRecordingDevices;
use App\Application\Build\BuildRewindHtml\BuildRewindHtml;
use App\Application\Build\BuildSegmentsHtml\BuildSegmentsHtml;
use App\Application\Build\ConfigureAppColors\ConfigureAppColors;
use App\Application\Build\ConfigureAppLocale\ConfigureAppLocale;
use App\Application\Import\StravaImport\ImportGear\GearImportStatus;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Console\ProgressBar;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Time\Clock\Clock;

final readonly class RunBuildCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandBus $commandBus,
        private AppStatusChecker $appStatusChecker,
        private GearImportStatus $gearImportStatus,
        private KeyValueStore $keyValueStore,
        private RenderCache $renderCache,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RunBuild);

        $output = $command->getOutput();
        $this->appStatusChecker->ensureIsReadyForBuild();

        if (!$this->gearImportStatus->isComplete()) {
            $output->block('[WARNING] Some of your gear hasn’t been imported yet. This is most likely due to Strava API rate limits being reached. As a result, your gear statistics may currently be incomplete.

This is not a bug, once all your activities have been imported, your gear statistics will update automatically and be complete.', null, 'fg=black;bg=yellow', ' ', true);
        }

        $now = $this->clock->getCurrentDateTimeImmutable();

        $output->writeln('Building app...');
        $output->newLine();

        $commandsWithMessages = [
            'Configuring locale' => new ConfigureAppLocale(),
            'Configuring theme colors' => new ConfigureAppColors(),
            'Building Manifest' => new BuildManifest(),
            'Building dashboard' => new BuildDashboardHtml(),
            'Building activities' => new BuildActivitiesHtml($now),
            'Building monthly stats' => new BuildMonthlyStatsHtml($now),
            'Building gear stats' => new BuildGearStatsHtml($now),
            'Building gear maintenance' => new BuildGearMaintenanceHtml(),
            'Building recording devices' => new BuildRecordingDevices(),
            'Building segments' => new BuildSegmentsHtml(),
            'Building best efforts' => new BuildBestEffortsHtml(),
            'Building rewind' => new BuildRewindHtml($now),
            'Building badges' => new BuildBadgeSvg($now),
        ];

        $progressBar = new ProgressBar($output, count($commandsWithMessages));
        $progressBar->start();

        foreach ($commandsWithMessages as $message => $command) {
            $progressBar->updateMessage($message);
            $progressBar->advance();
            $this->commandBus->dispatch($command);
        }

        $progressBar->finish();
        $output->writeln('');

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_DATE_TIME,
            value: Value::fromString($now->iso()),
        ));
        $this->renderCache->invalidateTags(CacheTag::APP_BUILD);
    }
}
