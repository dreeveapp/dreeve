<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class BestEffortsFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/best-efforts');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/best-efforts');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'best-efforts',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/best-efforts');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItShouldExpireAtMidnight(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/best-efforts');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-TTL', '27896');
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
