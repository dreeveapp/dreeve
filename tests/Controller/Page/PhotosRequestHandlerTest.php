<?php

namespace App\Tests\Controller\Page;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class PhotosRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/photos');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString(
            'photos.trust=',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activity.images');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
