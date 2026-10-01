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
        $this->seedActivity();

        $this->client->request('GET', '/rewind');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForASingleYear(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[DataProvider('provideUrls')]
    public function testItResolvesTheRewindOption(string $url, ?string $expectedCacheKey): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', $url);

        if (null === $expectedCacheKey) {
            $this->assertResponseStatusCodeSame(404);

            return;
        }

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith($expectedCacheKey, (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'));
    }

    public static function provideUrls(): \Generator
    {
        yield 'the bare path renders the first available option' => ['/rewind', 'rewind.all-time'];
        yield 'all time' => ['/rewind/all-time', 'rewind.all-time'];
        yield 'a year with activities' => ['/rewind/2023', 'rewind.2023'];
        yield 'a year without activities' => ['/rewind/2021', null];
        yield 'not a year at all' => ['/rewind/last-week', null];
    }

    public function testItMarksTheRewindSectionAsActive(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023');

        $this->assertStringContainsString('href="/rewind" aria-selected="true"', (string) $this->client->getResponse()->getContent());
    }

    public function testGetCacheabilityForAllTime(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/all-time');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'rewind.all-time',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertEqualsCanonicalizing(
            ['activities', 'activity.images', 'gear', 'settings.appearance', 'settings.general'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
    }

    public function testGetCacheabilityForASingleYearIsScopedToThatYear(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'rewind.2023',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertEqualsCanonicalizing(
            ['activities.2023', 'activity.images.2023', 'gear', 'settings.appearance', 'settings.general'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
