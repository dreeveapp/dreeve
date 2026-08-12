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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('console-output')]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:strava:webhooks-view', description: 'View Strava webhook subscription(s)')]
final class StravaViewWebhookSubscriptionConsoleCommand extends Command
{
    public function __construct(
        private readonly Strava $strava,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        if (!$subscriptions = $this->strava->getWebhookSubscription()) {
            $output->note('No webhook subscriptions found');
            $output->note('Create a subscription with: docker compose exec app bin/console app:strava:webhooks-create');

            return Command::SUCCESS;
        }

        $output->table(
            headers: ['ID', 'Application ID', 'Callback URL', 'Created At', 'Updated At'],
            rows: array_map(fn (array $subscription): array => [
                $subscription['id'],
                $subscription['application_id'],
                $subscription['callback_url'],
                $subscription['created_at'],
                $subscription['updated_at'],
            ], $subscriptions),
        );

        return Command::SUCCESS;
    }
}
