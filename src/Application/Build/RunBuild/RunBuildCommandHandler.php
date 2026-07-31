<?php

declare(strict_types=1);

namespace App\Application\Build\RunBuild;

use App\Application\AppStatusChecker;
use App\Application\Build\BuildActivitiesHtml\BuildActivitiesHtml;
use App\Application\Build\BuildBadgeSvg\BuildBadgeSvg;
use App\Application\Build\BuildBestEffortsHtml\BuildBestEffortsHtml;
use App\Application\Build\BuildChallengesHtml\BuildChallengesHtml;
use App\Application\Build\BuildDashboardHtml\BuildDashboardHtml;
use App\Application\Build\BuildEddingtonHtml\BuildEddingtonHtml;
use App\Application\Build\BuildGearMaintenanceHtml\BuildGearMaintenanceHtml;
use App\Application\Build\BuildGearStatsHtml\BuildGearStatsHtml;
use App\Application\Build\BuildGpxFiles\BuildGpxFiles;
use App\Application\Build\BuildHeatmapHtml\BuildHeatmapHtml;
use App\Application\Build\BuildIndexHtml\BuildIndexHtml;
use App\Application\Build\BuildManifest\BuildManifest;
use App\Application\Build\BuildMilestonesHtml\BuildMilestonesHtml;
use App\Application\Build\BuildMonthlyStatsHtml\BuildMonthlyStatsHtml;
use App\Application\Build\BuildRecordingDevices\BuildRecordingDevices;
use App\Application\Build\BuildRewindHtml\BuildRewindHtml;
use App\Application\Build\BuildSegmentsHtml\BuildSegmentsHtml;
use App\Application\Build\ConfigureAppColors\ConfigureAppColors;
use App\Application\Build\ConfigureAppLocale\ConfigureAppLocale;
use App\Application\Import\StravaImport\ImportGear\GearImportStatus;
use App\Infrastructure\Console\ProgressBar;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Time\Clock\Clock;

final readonly class RunBuildCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandBus $commandBus,
        private AppStatusChecker $appStatusChecker,
        private GearImportStatus $gearImportStatus,
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
            'Building index' => new BuildIndexHtml($now),
            'Building dashboard' => new BuildDashboardHtml(),
            'Building activities' => new BuildActivitiesHtml($now),
            'Building gpx files' => new BuildGpxFiles(),
            'Building monthly stats' => new BuildMonthlyStatsHtml($now),
            'Building gear stats' => new BuildGearStatsHtml($now),
            'Building gear maintenance' => new BuildGearMaintenanceHtml(),
            'Building recording devices' => new BuildRecordingDevices(),
            'Building eddington' => new BuildEddingtonHtml($now),
            'Building milestones' => new BuildMilestonesHtml(),
            'Building segments' => new BuildSegmentsHtml(),
            'Building heatmap' => new BuildHeatmapHtml($now),
            'Building best efforts' => new BuildBestEffortsHtml(),
            'Building rewind' => new BuildRewindHtml($now),
            'Building challenges' => new BuildChallengesHtml($now),
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
    }
}
