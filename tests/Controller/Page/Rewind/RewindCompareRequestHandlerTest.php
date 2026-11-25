<?php

namespace App\Tests\Controller\Page\Rewind;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\MatchesVersionlessSnapshots;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class RewindCompareRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesVersionlessSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/rewind/2023/compare/2022');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities.2022', 'activity.images.2022',
                'gear', 'settings.appearance',
            ],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param non-empty-string $expectedCacheKey
     */
    #[DataProvider('provideResolvableUrls')]
    public function testItResolvesBothRewindOptions(string $url, string $expectedCacheKey): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith($expectedCacheKey, (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'));
    }

    public static function provideResolvableUrls(): \Generator
    {
        yield 'two years' => ['/rewind/2023/compare/2022', 'rewind.2023.compare.2022'];
        yield 'a year and all time' => ['/rewind/2023/compare/all-time', 'rewind.2023.compare.all-time'];
        yield 'without a counterpart falls back to the first option' => ['/rewind/2023/compare', 'rewind.2023.compare.all-time'];
        yield 'all time without a counterpart falls back to the second option' => ['/rewind/all-time/compare', 'rewind.all-time.compare.2023'];
    }

    #[DataProvider('provideUnresolvableUrls')]
    public function testItReturnsNotFoundWhenTheRewindOptionsDoNotResolve(string $url): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function provideUnresolvableUrls(): \Generator
    {
        yield 'comparing an option with itself' => ['/rewind/2023/compare/2023'];
        yield 'a year without activities' => ['/rewind/2021/compare/2023'];
        yield 'a counterpart without activities' => ['/rewind/2023/compare/2021'];
    }

    public function testItDoesNotResolveWhenThereIsNothingToCompare(): void
    {
        $this->addActivityOneFixtures();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/all-time');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetCacheabilityWhenComparedWithAllTime(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/rewind/2023/compare/all-time');

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities', 'activity.images',
                'gear', 'settings.appearance',
            ],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
