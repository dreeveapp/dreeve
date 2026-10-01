<?php

namespace App\Tests\Controller\Page\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class CompareActivitiesRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRenderWithoutActivities(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithOneActivity(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare?activities=activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare?activities=activity-9756441741,activity-9542782314');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItEmbedsTheDatasetForActivityNamesWithQuotes(): void
    {
        foreach (['1' => "Robin's \"loop\"", '2' => "<b>Robin's</b> 'loop'"] as $id => $name) {
            static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withName($name)
                    ->build(),
                [],
            ));
        }

        $crawler = $this->client->request('GET', '/activities/compare?activities=activity-1,activity-2');

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            ["Robin's \"loop\"", "<b>Robin's</b> 'loop'"],
            array_column(Json::decode((string) $crawler->filter('[data-metric-explorer]')->attr('data-metric-explorer-dataset'))['rows'], 'label'),
        );
    }

    public function testItIgnoresUnknownAndInvalidActivityIds(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare?activities=activity-9756441741');
        $expected = $this->client->getResponse()->getContent();

        $this->client->request('GET', '/activities/compare?activities=activity-1,9542782314,,activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertSame($expected, $this->client->getResponse()->getContent());
    }

    public function testItIsNotCached(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare?activities=activity-9756441741,activity-9542782314');

        $this->assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $this->assertFalse($this->client->getResponse()->headers->has('X-Dreeve-Cache-Key'));
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
