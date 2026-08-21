<?php

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ChatFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    public function testRender(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/api/internal/fragment/page/chat');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotFoundWhenTheAssistantIsDisabled(): void
    {
        $this->enableAssistant(false);

        $this->client->request('GET', '/api/internal/fragment/page/chat');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/api/internal/fragment/data/chat');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNeverServedFromCache(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/api/internal/fragment/page/chat');
        $this->assertResponseHeaderSame('X-Dreeve-Cache', 'MISS');

        $this->client->request('GET', '/api/internal/fragment/page/chat');
        $this->assertResponseHeaderSame('X-Dreeve-Cache', 'MISS');
    }

    private function enableAssistant(bool $enabled): void
    {
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            SettingsGroup::INTEGRATIONS->keyValueKey(),
            Value::fromString(Json::encode([
                'ai' => [
                    'enabled' => true,
                    'enableUI' => $enabled,
                    'provider' => 'openAI',
                    'configuration' => [
                        'key' => 'my-key',
                        'model' => 'cool-model',
                    ],
                ],
            ])),
        ));
    }
}
