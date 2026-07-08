<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Console\Daemon\AppUpdateAvailableNotificationCronAction;
use App\Console\Daemon\GearMaintenanceNotificationConsoleCommand;
use App\Console\Daemon\RunStravaImportAndBuildAppConsoleCommand;
use App\Domain\Import\ImportMode;
use Cron\CronExpression;

final readonly class CronAction
{
    private function __construct(
        private string $id,
        private CronExpression $expression,
    ) {
    }

    public static function create(
        string $id,
        CronExpression $expression,
    ): self {
        return new self(
            id: $id,
            expression: $expression,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpression(): CronExpression
    {
        return $this->expression;
    }

    public function getCommand(): string
    {
        return match ($this->getId()) {
            'importDataAndBuildApp' => sprintf('bin/console %s', RunStravaImportAndBuildAppConsoleCommand::NAME),
            'gearMaintenanceNotification' => sprintf('bin/console %s', GearMaintenanceNotificationConsoleCommand::NAME),
            'appUpdateAvailableNotification' => sprintf('bin/console %s', AppUpdateAvailableNotificationCronAction::NAME),
            default => throw new \RuntimeException(sprintf('Unsupported Cron action: %s', $this->getId())),
        };
    }

    public function supportsImportMode(ImportMode $importMode): bool
    {
        if ('importDataAndBuildApp' !== $this->getId()) {
            return true;
        }

        return !$importMode->isFiles();
    }
}
