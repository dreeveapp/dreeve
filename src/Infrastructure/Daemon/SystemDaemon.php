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
        $actions = [];
        $processedCronAction = [];
        /** @var CronAction $cronAction */
        foreach ($this->settingsRepository->daemon()->getConfiguredCronActions() as $cronAction) {
            if (!$cronAction->supportsImportMode($this->importMode)) {
                continue;
            }
            $processedCronAction[] = $cronAction;
            $actions[] = new Action(
                key: $cronAction->getId()->value,
                mutexTtl: 1200,
                expression: (string) $cronAction->getExpression(),
                performer: function () use ($cronAction): PromiseInterface {
                    $process = new CronProcess(
                        cronActionId: $cronAction->getId()->value,
                        clock: $this->clock,
                        output: $this->getConsoleOutput(),
                        command: $cronAction->getCommand(),
                    );
                    $process->start();

                    return resolve(true);
                }
            );
        }

        $extraConfiguredCronActionsOutput = [];

        if ($this->importMode->isFiles()) {
            $extraConfiguredCronActionsOutput[] = sprintf('<info> - runFileImport: %s</info>', self::CRON_EVERY_5_MINUTES);
            $actions[] = new Action(
                key: 'runFileImport',
                mutexTtl: 300,
                expression: self::CRON_EVERY_5_MINUTES,
                performer: function (): PromiseInterface {
                    $process = new CronProcess(
                        cronActionId: 'runFileImport',
                        clock: $this->clock,
                        output: $this->getConsoleOutput(),
                        command: sprintf('bin/console %s', RunFileImportConsoleCommand::NAME)
                    );
                    $process->start();

                    return resolve(true);
                }
            );

            $extraConfiguredCronActionsOutput[] = sprintf('<info> - runAutomationRulesBackfill: %s</info>', self::CRON_EVERY_5_MINUTES);
            $actions[] = new Action(
                key: 'runAutomationRulesBackfill',
                mutexTtl: 300,
                expression: self::CRON_EVERY_5_MINUTES,
                performer: function (): PromiseInterface {
                    $process = new CronProcess(
                        cronActionId: 'runAutomationRulesBackfill',
                        clock: $this->clock,
                        output: $this->getConsoleOutput(),
                        command: sprintf('bin/console %s', RunAutomationRulesBackfillConsoleCommand::NAME)
                    );
                    $process->start();

                    return resolve(true);
                }
            );
        }

        $extraConfiguredCronActionsOutput[] = sprintf('<info> - pruneRenderCache: %s</info>', self::CRON_DAILY);
        $actions[] = new Action(
            key: 'pruneRenderCache',
            mutexTtl: 300,
            expression: self::CRON_DAILY,
            performer: function (): PromiseInterface {
                $process = new CronProcess(
                    cronActionId: 'pruneRenderCache',
                    clock: $this->clock,
                    output: $this->getConsoleOutput(),
                    command: sprintf('bin/console %s', PruneRenderCacheConsoleCommand::NAME),
                );
                $process->start();

                return resolve(true);
            }
        );

        $webhookConfig = $this->settingsRepository->import()->getWebhookConfig();
        if ($this->importMode->isStravaApi() && $webhookConfig->isEnabled()) {
            $extraConfiguredCronActionsOutput[] = sprintf('<info> - processStravaWebhooks: %s</info>', $webhookConfig->getCronExpression());
            $actions[] = new Action(
                key: 'processStravaWebhooks',
                mutexTtl: 60,
                expression: (string) $webhookConfig->getCronExpression(),
                performer: function (): PromiseInterface {
                    $process = new CronProcess(
                        cronActionId: 'processStravaWebhooks',
                        clock: $this->clock,
                        output: $this->getConsoleOutput(),
                        command: sprintf('bin/console %s', ProcessStravaWebhooksConsoleCommand::NAME)
                    );
                    $process->start();

                    return resolve(true);
                }
            );
        }

        \WyriHaximus\React\Cron::create(...$actions)->on('error', function (\Throwable $throwable): void {
            $this->getConsoleOutput()->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
        });

        $this->getConsoleOutput()->writeln(sprintf('<info>%s</info>', 'Cron configured'));
        $this->getConsoleOutput()->writeln([
            ...array_map(
                fn (CronAction $action): string => \sprintf('<info> - %s: %s</info>', $action->getId()->value, $action->getExpression()),
                $processedCronAction
            ),
            ...$extraConfiguredCronActionsOutput,
        ]);
    }
}
