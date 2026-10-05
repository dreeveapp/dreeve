<?php

namespace App\Tests\Domain\Strava\Webhook\ProcessWebhookEvent;

use App\Domain\Strava\Webhook\ProcessWebhookEvent\ProcessWebhookEvent;
use App\Domain\Strava\Webhook\WebhookAspectType;
use App\Domain\Strava\Webhook\WebhookEvent;
use App\Domain\Strava\Webhook\WebhookEventRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;

class ProcessWebhookEventCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(new ProcessWebhookEvent([
            'object_id' => 1,
            'object_type' => 'activity',
            'aspect_type' => 'create',
        ]));

        $this->assertEquals(
            [
                WebhookEvent::create(
                    objectId: '1',
                    objectType: 'activity',
                    aspectType: WebhookAspectType::CREATE,
                    payload: [
                        'object_id' => 1,
                        'object_type' => 'activity',
                        'aspect_type' => 'create',
                    ],
                ),
            ],
            $this->getContainer()->get(WebhookEventRepository::class)->grab()
        );
    }

    public function testHandleWhenNotActivityEvent(): void
    {
        $this->commandBus->dispatch(new ProcessWebhookEvent([
            'object_id' => 1,
            'object_type' => 'athlete',
            'aspect_type' => 'create',
        ]));

        $this->assertEmpty(
            $this->getContainer()->get(WebhookEventRepository::class)->grab()
        );
    }

    public function testHandleWhenInvalidAspectType(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Aspect type "invalid" not supported'));
        $this->commandBus->dispatch(new ProcessWebhookEvent([
            'object_id' => 1,
            'object_type' => 'activity',
            'aspect_type' => 'invalid',
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
