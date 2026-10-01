<?php

namespace App\Tests\Controller\Page\Activity;

use App\Tests\Controller\Admin\AdminWebTestCase;
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

    public function testItIsCachedPerSelection(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/activities/compare');
        $this->assertStringEndsWith(
            '.activities.compare',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );

        $this->client->request('GET', '/activities/compare?activities=activity-9756441741,activity-9542782314,activity-9542782314');
        $this->assertStringEndsWith(
            '.activities.compare.9542782314.9756441741',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities, activities.9542782314, activities.9756441741',
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
