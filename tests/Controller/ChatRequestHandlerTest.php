<?php

namespace App\Tests\Controller;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Tests\Controller\Admin\AdminWebTestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ChatRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;

    public function testRender(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotFoundWhenTheAssistantIsDisabled(): void
    {
        $this->enableAssistant(false);

        $this->client->request('GET', '/chat');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNotRenderCached(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');

        $this->assertResponseIsSuccessful();
        $this->assertResponseNotHasHeader('X-Dreeve-Cache');
    }

    private function enableAssistant(bool $enabled): void
    {
        $this->getContainer()->get(DbalSettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'enableUI' => $enabled,
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'my-key',
                    'model' => 'cool-model',
                ],
            ],
        ]);
    }

    public function testItOnlyRendersTheAdminLinkForAuthenticatedVisitors(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');
        $this->assertStringNotContainsString(
            'admin/settings/integrations',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/chat');
        $this->assertStringContainsString(
            'admin/settings/integrations?redirectTo=%2Fchat',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItIsNeverStoredByTheBrowser(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');

        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');
    }
}
