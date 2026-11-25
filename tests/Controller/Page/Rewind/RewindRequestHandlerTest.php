<?php

namespace App\Tests\Controller\Page\Rewind;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RewindRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRenderForAllTime(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/rewind');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForASingleYear(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/rewind/2023');

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            ['activities.2023', 'activity.images.2023', 'gear', 'settings.appearance'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param non-empty-string $expectedCacheKey
     */
    #[DataProvider('provideResolvableUrls')]
    public function testItResolvesTheRewindOption(string $url, string $expectedCacheKey): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith($expectedCacheKey, (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'));
    }

    public static function provideResolvableUrls(): \Generator
    {
        yield 'the bare path renders the first available option' => ['/rewind', 'rewind.all-time'];
        yield 'all time' => ['/rewind/all-time', 'rewind.all-time'];
        yield 'a year with activities' => ['/rewind/2023', 'rewind.2023'];
    }

    #[DataProvider('provideUnresolvableUrls')]
    public function testItReturnsNotFoundWhenTheRewindOptionDoesNotResolve(string $url): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function provideUnresolvableUrls(): \Generator
    {
        yield 'a year without activities' => ['/rewind/2021'];
        yield 'not a year at all' => ['/rewind/last-week'];
    }

    public function testGetCacheabilityForAllTime(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/rewind/all-time');

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            ['activities', 'activity.images', 'gear', 'settings.appearance'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
