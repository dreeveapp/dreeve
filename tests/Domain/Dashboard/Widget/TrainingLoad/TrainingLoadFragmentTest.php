<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class TrainingLoadFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/training-load');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotServedAsAPartialFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/partial/training-load');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheActivitiesItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/training-load');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
    }
}
