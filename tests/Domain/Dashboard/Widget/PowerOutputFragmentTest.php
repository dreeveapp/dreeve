<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class PowerOutputFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/power-output');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotServedAsAPartialFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/partial/power-output');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheActivitiesItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/power-output');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
    }
}
