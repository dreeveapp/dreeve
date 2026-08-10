<?php

namespace App\Tests\Domain\Rewind;

use App\Domain\Rewind\RewindFragmentResolver;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RewindFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRenderForAllTime(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/rewind');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForASingleYear(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/rewind/2023');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/rewind/2023');

        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('providePathsToResolve')]
    public function testResolve(string $path, ?string $expectedPath): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->assertEquals(
            $expectedPath,
            $this->getContainer()->get(RewindFragmentResolver::class)->resolve($path)?->getPath()
        );
    }

    public static function providePathsToResolve(): \Generator
    {
        yield 'the bare path renders the first available option' => ['rewind', 'rewind/all-time'];
        yield 'all time' => ['rewind/all-time', 'rewind/all-time'];
        yield 'a year with activities' => ['rewind/2023', 'rewind/2023'];
        yield 'a year without activities' => ['rewind/2021', null];
        yield 'not a year at all' => ['rewind/last-week', null];
        yield 'a comparison is served by another page' => ['rewind/2023/compare/2022', null];
        yield 'another page entirely' => ['milestones', null];
    }

    public function testGetCacheabilityForAllTime(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/rewind/all-time');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'rewind.all-time',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
        $this->assertEqualsCanonicalizing(
            ['activities', 'activity.images', 'gear', 'settings.appearance', 'settings.general'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Cache-Tags')),
        );
    }

    public function testGetCacheabilityForASingleYearIsScopedToThatYear(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/rewind/2023');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'rewind.2023',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
        $this->assertEqualsCanonicalizing(
            ['activities.2023', 'activity.images.2023', 'gear', 'settings.appearance', 'settings.general'],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Cache-Tags')),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
