<?php

namespace App\Tests\Domain\Challenge;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideBuiltTestSet;
use Spatie\Snapshots\MatchesSnapshots;

class ChallengesFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideBuiltTestSet;

    public function testRender(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/challenges');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/challenges');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'challenges',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/challenges');

        $this->assertResponseStatusCodeSame(404);
    }
}
