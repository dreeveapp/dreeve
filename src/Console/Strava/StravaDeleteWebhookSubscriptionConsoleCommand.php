<?php

declare(strict_types=1);

namespace App\Console\Strava;

use App\Domain\Strava\Strava;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('console-output')]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:strava:webhooks-delete', description: 'Delete a Strava webhook subscription', aliases: ['app:strava:webhooks-unsubscribe'])]
final class StravaDeleteWebhookSubscriptionConsoleCommand extends Command
{
    public function __construct(
        private readonly Strava $strava,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('subscriptionId', InputArgument::REQUIRED, 'The webhook subscription ID to delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));
        $subscriptionId = $input->getArgument('subscriptionId');

        if (!$output->confirm(sprintf('Are you sure you want to delete subscription with ID %s?', $subscriptionId), false)) {
            return Command::SUCCESS;
        }

        $this->strava->deleteWebhookSubscription($subscriptionId);

        $output->success('Webhook subscription deleted successfully!');
        $output->comment('You will no longer receive automatic updates from Strava.');

        return Command::SUCCESS;
    }
}
