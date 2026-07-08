<?php

namespace App\Tests\Controller\Strava;

use App\Controller\Strava\StravaWebhookRequestHandler;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StravaWebhookRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StravaWebhookRequestHandler $stravaWebhookRequestHandler;
    private SpyCommandBus $commandBus;
    private MockObject $logger;

    public function testHandleValidation(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $response = $this->stravaWebhookRequestHandler->handleValidation(new Request(
            query: [
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-token',
            ],
        ));

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(
            ['hub.challenge' => 'test-challenge-123'],
            Json::decode($response->getContent())
        );
        $this->assertEmpty($this->commandBus->getDispatchedCommands());
    }

    public function testHandleValidationWithInvalidVerifyToken(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        $response = $this->stravaWebhookRequestHandler->handleValidation(new Request(
            query: [
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-no-token',
            ],
        ));

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEmpty($this->commandBus->getDispatchedCommands());
    }

    public function testHandleValidationWhenWebhooksDisabled(): void
    {
        $this->logger
            ->expects($this->never())
            ->method('info');

        $stravaWebhookRequestHandler = new StravaWebhookRequestHandler(
            $this->settingsWithWebhooks(enabled: false, verifyToken: 'el-token'),
            $this->commandBus,
            $this->logger,
        );
        $response = $stravaWebhookRequestHandler->handleValidation(new Request(
            query: [
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-no-token',
            ],
        ));

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertEmpty($this->commandBus->getDispatchedCommands());
    }

    public function testHandleEvent(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->logger
            ->expects($this->never())
            ->method('error');

        $response = $this->stravaWebhookRequestHandler->handleEvent(new Request(
            content: Json::encode([
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-token',
            ]),
        ));

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testHandleEventOnException(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        $commandBus = $this->createMock(CommandBus::class);

        $commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \Exception('Something happened'));

        $stravaWebhookRequestHandler = new StravaWebhookRequestHandler(
            $this->settingsWithWebhooks(enabled: true, verifyToken: 'el-token'),
            $commandBus,
            $this->logger,
        );

        $response = $stravaWebhookRequestHandler->handleEvent(new Request(
            content: Json::encode([
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-token',
            ]),
        ));

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleEventWhenWebhooksDisabled(): void
    {
        $this->logger
            ->expects($this->never())
            ->method('info');

        $stravaWebhookRequestHandler = new StravaWebhookRequestHandler(
            $this->settingsWithWebhooks(enabled: false, verifyToken: 'el-token'),
            $this->commandBus,
            $this->logger,
        );

        $response = $stravaWebhookRequestHandler->handleEvent(new Request(
            content: Json::encode([
                'hub_mode' => 'subscribe',
                'hub_challenge' => 'test-challenge-123',
                'hub_verify_token' => 'el-token',
            ]),
        ));

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stravaWebhookRequestHandler = new StravaWebhookRequestHandler(
            $this->settingsWithWebhooks(enabled: true, verifyToken: 'el-token'),
            $this->commandBus = new SpyCommandBus(),
            $this->logger = $this->createMock(LoggerInterface::class),
        );
    }

    private function settingsWithWebhooks(bool $enabled, string $verifyToken): SettingsRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::IMPORT, [
            'webhooks' => ['enabled' => $enabled, 'verifyToken' => $verifyToken],
        ]);

        return $settingsRepository;
    }
}
