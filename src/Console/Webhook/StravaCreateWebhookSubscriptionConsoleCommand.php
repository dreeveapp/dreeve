<?php

declare(strict_types=1);

namespace App\Console\Webhook;

use App\Application\AppUrl;
use App\Controller\Strava\StravaWebhookRequestHandler;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\ValueObject\String\Url;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('console-output')]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:strava:webhooks-create', description: 'Create a Strava webhook subscription')]
final class StravaCreateWebhookSubscriptionConsoleCommand extends Command
{
    public function __construct(
        private readonly Strava $strava,
        private readonly SettingsRepository $settingsRepository,
        private readonly AppUrl $appUrl,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        $webhookConfig = $this->settingsRepository->import()->getWebhookConfig();
        if (!$webhookConfig->isEnabled()) {
            // Do not allow to create a webhook subscription as the validation request handler won't work.
            $output->warning('Webhooks not enabled. Enable them by setting import.webhooks.enabled = true');

            return Command::SUCCESS;
        }

        $callbackUrl = Url::fromString(rtrim((string) $this->appUrl, '/').StravaWebhookRequestHandler::STRAVA_WEBHOOKS_ENDPOINT);
        $output->comment(sprintf('Trying to configure Strava webhooks on URL %s', $callbackUrl));

        $this->strava->createWebhookSubscription(
            callbackUrl: $callbackUrl,
            verifyToken: $webhookConfig->getVerifyToken(),
        );

        $output->success('Webhook subscription created successfully!');
        $output->comment('The app will automatically import and build when receiving activity events.');
        $output->comment(sprintf('The configured callback URL is %s', $callbackUrl));

        return Command::SUCCESS;
    }
}
