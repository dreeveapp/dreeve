<?php

namespace App\Tests\Domain\Activity\Eddington;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class EddingtonFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/eddington');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/eddington');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'eddington',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/eddington');
        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
