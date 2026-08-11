<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill\RunAutomationRulesBackfill;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class RunAutomationRulesBackfillCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private AutomationRuleEngine $automationRuleEngine,
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRulesBackfillQueue $queue,
        private CommandBus $commandBus,
        private AppUrl $appUrl,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RunAutomationRulesBackfill);

        $output = $command->getOutput();
        $output->writeln('Applying automation rules to existing activities...');

        $request = $this->queue->find();
        $activityIds = $request->getActivityIds();
        $rules = $this->automationRuleRepository->findAll()->only($request->getAutomationRuleIds());
        $updatedCount = 0;

        foreach ($activityIds as $activityId) {
            try {
                $activityWithRawData = $this->activityRepository->findWithRawData($activityId);
            } catch (EntityNotFound) {
                continue;
            }

            $activity = $activityWithRawData->getActivity();

            $this->activityRepository->update(ActivityWithRawData::fromState(
                activity: $this->automationRuleEngine->apply($activity, $rules),
                rawData: $activityWithRawData->getRawData(),
            ));
            ++$updatedCount;

            $output->writeln(sprintf('  => Updated "%s"', $activity->getName()));
        }

        $this->queue->clear();

        $message = sprintf('Automation rules applied to %d activities', $updatedCount);
        $output->writeln($message);

        $this->commandBus->dispatch(new SendNotification(
            title: 'Automation rules applied',
            message: $message,
            tags: ['+1'],
            actionUrl: $this->appUrl
        ));
    }
}
