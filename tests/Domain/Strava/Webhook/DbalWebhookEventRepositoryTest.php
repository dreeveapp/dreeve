<?php

namespace App\Tests\Domain\Strava\Webhook;

use App\Domain\Strava\Webhook\DbalWebhookEventRepository;
use App\Domain\Strava\Webhook\WebhookAspectType;
use App\Domain\Strava\Webhook\WebhookEvent;
use App\Domain\Strava\Webhook\WebhookEventRepository;
use App\Tests\ContainerTestCase;

class DbalWebhookEventRepositoryTest extends ContainerTestCase
{
    private WebhookEventRepository $webhookEventRepository;

    public function testAddAndGrab(): void
    {
        $event = WebhookEvent::create(
            objectId: '1',
            objectType: 'activity',
            aspectType: WebhookAspectType::CREATE,
            payload: [],
        );

        $this->webhookEventRepository->add($event);
        $this->webhookEventRepository->add($event);

        $event = WebhookEvent::create(
            objectId: '2',
            objectType: 'activity',
            aspectType: WebhookAspectType::UPDATE,
            payload: [],
        );

        $this->webhookEventRepository->add($event);

        $this->assertNotEmpty($this->webhookEventRepository->grab());
        $this->assertEmpty($this->webhookEventRepository->grab());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookEventRepository = new DbalWebhookEventRepository($this->getConnection());
    }
}
