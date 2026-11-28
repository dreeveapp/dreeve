<?php

namespace App\Tests\Controller\Admin\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Integration\AI\AIPlatformFactory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SuggestActivityMediaItemLocationRequestHandlerTest extends AdminWebTestCase
{
    private ?string $originalAIApiKey = null;

    public function testSuggestsWhereOnTheRouteTheMediaItemWasTaken(): void
    {
        $requestBodies = [];
        $responses = [
            new MockResponse((string) file_get_contents(__DIR__.'/../../../../Domain/Integration/AI/MediaLocation/fixtures/anthropic-response-with-suggestion.json')),
            new MockResponse((string) file_get_contents(__DIR__.'/../../../../Domain/Integration/AI/MediaLocation/fixtures/anthropic-response-without-suggestion.json')),
            new MockResponse('{"type": "error", "error": {"type": "api_error", "message": "Overloaded"}}', ['http_code' => 500]),
        ];
        static::getContainer()->set(AIPlatformFactory::class, new AIPlatformFactory(
            settingsRepository: static::getContainer()->get(SettingsRepository::class),
            httpClient: new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestBodies, &$responses): MockResponse {
                $requestBodies[] = Json::decode($options['body']);

                return array_shift($responses) ?? throw new \LogicException('Unexpected request to the AI provider');
            }),
        ));

        static::getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('photo'))
            ->withPath(MediaItemPath::fromString('activities/photo.jpg'))
            ->build());
        static::getContainer()->get('file.storage')->write('activities/photo.jpg', (string) file_get_contents(__DIR__.'/../../../../Domain/Integration/AI/MediaLocation/fixtures/photo-with-orientation-6.jpg'));
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('photo'))
                ->build(),
            [],
        ));
        $activityStreamRepository = static::getContainer()->get(ActivityStreamRepository::class);
        foreach ([
            StreamType::TIME->value => [0, 600, 1200],
            StreamType::DISTANCE->value => [0, 4000, 8000],
            StreamType::LAT_LNG->value => [[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]],
        ] as $streamType => $data) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride'))
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }

        $this->client->loginUser($this->adminUser());
        $csrfToken = (string) $this->client->request('GET', '/admin/upload')->filter('meta[name="csrf-token"]')->attr('content');
        $url = '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('photo').'/suggest-location';

        $this->client->request('POST', $url, ['takenOn' => '2026-06-01T10:00:15'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(404);

        static::getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'provider' => 'anthropic',
                'configuration' => ['model' => 'model'],
            ],
        ]);

        $this->client->request('POST', $url, ['takenOn' => '2026-06-01T10:00:15'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(404);

        static::getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'provider' => 'anthropic',
                'configuration' => ['model' => 'model'],
                'mediaLocation' => ['enabled' => '1'],
            ],
        ]);

        $this->client->request('POST', $url, ['takenOn' => '2026-06-01T10:00:15'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [
                'fields' => ['latitude' => 51.5, 'longitude' => 5.5],
                'message' => 'The photo shows a summit cairn, which matches the highest point of the route around km 6. (confidence: high)',
            ],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
        $this->assertStringContainsString('Taken on: 2026-06-01 10:00:15', $requestBodies[0]['messages'][0]['content'][0]['text']);

        $this->client->request('POST', $url, ['takenOn' => 'not-a-date'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['error' => 'Your AI provider could not tell where on the route this photo was taken.'],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
        $this->assertStringContainsString('Taken on: unknown', $requestBodies[1]['messages'][0]['content'][0]['text']);

        $this->client->request('POST', $url, ['takenOn' => ''], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringStartsWith(
            'Your AI provider could not suggest a location: ',
            Json::decode((string) $this->client->getResponse()->getContent())['error'],
        );

        $this->client->request('POST', $url, server: ['HTTP_X_CSRF_TOKEN' => 'a-tampered-token']);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('other').'/suggest-location', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/activities/'.ActivityId::fromUnprefixed('unknown').'/media/'.MediaItemId::fromUnprefixed('photo').'/suggest-location', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        $this->assertResponseStatusCodeSame(404);

        $this->assertCount(3, $requestBodies);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAIApiKey = $_SERVER['AI_API_KEY'];
        $_SERVER['AI_API_KEY'] = 'sk-key';
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        $_SERVER['AI_API_KEY'] = $this->originalAIApiKey;
    }
}
