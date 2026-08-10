<?php

namespace App\Tests\Domain\Badge;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class BadgeFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRenderDreeveBadge(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        // An activity name that has to survive both the ellipsing and the escaping.
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withName('🕍➡️⛱️➡️🚜 Climb Portal: Côte de la Redoute')
                ->withStartDateTime(SerializableDateTime::fromString('2025-05-17'))
                ->build(),
            rawData: []
        ));

        $this->client->request('GET', '/badge/dreeve.svg');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderZwiftBadge(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/zwift.svg');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderPersonalBestBadge(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/pb/ride.svg');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderPersonalBestBadgeForVirtualRide(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/pb/virtualride.svg');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/dreeve.svg');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'badge.dreeve',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsTaggedWithWhatItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/dreeve.svg');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities, challenges',
        );
    }

    public function testItDoesNotResolveTheZwiftBadgeWithoutAConfiguredLevel(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            SettingsGroup::ZWIFT->keyValueKey(),
            Value::fromString(Json::encode(['level' => null, 'racingScore' => null])),
        ));

        $this->client->request('GET', '/badge/zwift.svg');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASportTypeWithoutBestEfforts(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/pb/walk.svg');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnUnknownSportType(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/badge/pb/unicycling.svg');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/badge/dreeve');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
