<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon;

use App\Console\Cache\PruneRenderCacheConsoleCommand;
use App\Console\Import\RunAutomationRulesBackfillConsoleCommand;
use App\Console\Import\RunFileImportConsoleCommand;
use App\Console\Strava\ProcessStravaWebhooksConsoleCommand;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Console\ConsoleOutputAware;
use App\Infrastructure\Daemon\Cron\CronAction;
use App\Infrastructure\Daemon\Cron\CronProcess;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\Clock\Clock;
use Doctrine\DBAL\Connection;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use WyriHaximus\React\Cron\Action;

use function React\Promise\resolve;

/**
 * @codeCoverageIgnore
 */
final class SystemDaemon implements Daemon
{
    use ConsoleOutputAware;

    private const string CRON_EVERY_5_MINUTES = '*/5 * * * *';
    private const string CRON_DAILY = '0 4 * * *';

    public function __construct(
        private readonly Clock $clock,
        private readonly SettingsRepository $settingsRepository,
        private readonly ImportMode $importMode,
        private readonly Connection $connection,
    ) {
    }

    public function addPeriodicDebugTimer(): void
    {
        Loop::addPeriodicTimer(1.0, function (): void {
            $this->getConsoleOutput()->writeln(sprintf(
                '[%s] Periodic debug timer',
                $this->clock->getCurrentDateTimeImmutable()->format('H:i:s'),
            ));
        });
    }

    public function clearStaleCronLocks(): void
    {
        // On startup no cron child process is running yet, so any lock still present
        // in the KeyValue table is stale.
        foreach (LockName::cases() as $lockName) {
            new Mutex(
                connection: $this->connection,
                clock: $this->clock,
                lockName: $lockName,
            )->releaseLock();
        }
    }

    public function configureCron(): void
    {
        /** @var list<array{key: string, mutexTtl: int, expression: string, command: string}> $jobs */
        $jobs = [];
        /** @var CronAction $cronAction */
        foreach ($this->settingsRepository->daemon()->getConfiguredCronActions() as $cronAction) {
            if (!$cronAction->supportsImportMode($this->importMode)) {
                continue;
            }
            $jobs[] = [
                'key' => $cronAction->getId()->value,
                'mutexTtl' => 1200,
                'expression' => (string) $cronAction->getExpression(),
                'command' => $cronAction->getCommand(),
            ];
        }

        if ($this->importMode->isFiles()) {
            $jobs[] = [
                'key' => 'runFileImport',
                'mutexTtl' => 300,
                'expression' => self::CRON_EVERY_5_MINUTES,
                'command' => sprintf('bin/console %s', RunFileImportConsoleCommand::NAME),
            ];
            $jobs[] = [
                'key' => 'runAutomationRulesBackfill',
                'mutexTtl' => 300,
                'expression' => self::CRON_EVERY_5_MINUTES,
                'command' => sprintf('bin/console %s', RunAutomationRulesBackfillConsoleCommand::NAME),
            ];
        }

        $jobs[] = [
            'key' => 'pruneRenderCache',
            'mutexTtl' => 300,
            'expression' => self::CRON_DAILY,
            'command' => sprintf('bin/console %s', PruneRenderCacheConsoleCommand::NAME),
        ];

        $webhookConfig = $this->settingsRepository->import()->getWebhookConfig();
        if ($this->importMode->isStravaApi() && $webhookConfig->isEnabled()) {
            $jobs[] = [
                'key' => 'processStravaWebhooks',
                'mutexTtl' => 60,
                'expression' => (string) $webhookConfig->getCronExpression(),
                'command' => sprintf('bin/console %s', ProcessStravaWebhooksConsoleCommand::NAME),
            ];
        }

        $actions = [];
        $output = [];
        foreach ($jobs as $job) {
            $actions[] = new Action(
                key: $job['key'],
                mutexTtl: $job['mutexTtl'],
                expression: $job['expression'],
                performer: function () use ($job): PromiseInterface {
                    new CronProcess(
                        cronActionId: $job['key'],
                        clock: $this->clock,
                        output: $this->getConsoleOutput(),
                        command: $job['command'],
                    )->start();

                    return resolve(true);
                }
            );
            $output[] = sprintf('<info> - %s: %s</info>', $job['key'], $job['expression']);
        }

        \WyriHaximus\React\Cron::create(...$actions)->on('error', function (\Throwable $throwable): void {
            $this->getConsoleOutput()->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
        });

        $this->getConsoleOutput()->writeln(sprintf('<info>%s</info>', 'Cron configured'));
        $this->getConsoleOutput()->writeln($output);
    }
}
