<?php

declare(strict_types=1);

namespace App\Controller\Strava;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Webhook\ProcessWebhookEvent\ProcessWebhookEvent;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Serialization\Json;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[WithMonologChannel('webhooks')]
final readonly class StravaWebhookRequestHandler
{
    public const string STRAVA_WEBHOOKS_ENDPOINT = '/strava/webhook';

    public function __construct(
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: self::STRAVA_WEBHOOKS_ENDPOINT, methods: ['GET'], priority: 2)]
    public function handleValidation(Request $request): JsonResponse
    {
        $webhookConfig = $this->settingsRepository->import()->getWebhookConfig();
        if (!$webhookConfig->isEnabled()) {
            return new JsonResponse('Webhooks are not enabled', Response::HTTP_NOT_FOUND);
        }

        $mode = $request->query->get('hub_mode');
        $challenge = $request->query->get('hub_challenge');
        $verifyToken = $request->query->get('hub_verify_token');

        $this->logger->info('Received Strava webhook validation request', [
            'hub.mode' => $mode,
            'hub.challenge' => $challenge,
            'hub.verify_token' => $verifyToken,
            'all' => $request->query->all(),
        ]);

        if ($verifyToken !== $webhookConfig->getVerifyToken()) {
            $this->logger->error('Invalid verify token received', [
                'expected' => $webhookConfig->getVerifyToken(),
                'received' => $verifyToken,
            ]);

            return new JsonResponse('Invalid verify token', Response::HTTP_FORBIDDEN);
        }

        $this->logger->info('Validated Strava webhook request');

        return new JsonResponse(['hub.challenge' => $challenge], Response::HTTP_OK);
    }

    #[Route(path: self::STRAVA_WEBHOOKS_ENDPOINT, methods: ['POST'], priority: 2)]
    public function handleEvent(Request $request): Response
    {
        if (!$this->settingsRepository->import()->getWebhookConfig()->isEnabled()) {
            return new JsonResponse('Webhooks are not enabled', Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = Json::decode($request->getContent());

            $this->logger->info('Received Strava webhook event', [
                'payload' => $payload,
            ]);

            $this->commandBus->dispatch(new ProcessWebhookEvent($payload));

            return new Response('', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return 200 to prevent Strava from retrying
            return new Response('', Response::HTTP_OK);
        }
    }
}
