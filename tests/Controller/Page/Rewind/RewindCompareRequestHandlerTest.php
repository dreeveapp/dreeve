<?php

namespace App\Tests\Controller\Page\Rewind;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RewindCompareRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/2022');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[DataProvider('provideUrls')]
    public function testItResolvesBothRewindOptions(string $url, ?string $expectedCacheKey): void
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
        yield 'two years' => ['/rewind/2023/compare/2022', 'rewind.2023.compare.2022'];
        yield 'a year and all time' => ['/rewind/2023/compare/all-time', 'rewind.2023.compare.all-time'];
        yield 'without a counterpart falls back to the first option' => ['/rewind/2023/compare', 'rewind.2023.compare.all-time'];
        yield 'all time without a counterpart falls back to the second option' => ['/rewind/all-time/compare', 'rewind.all-time.compare.2023'];
        yield 'comparing an option with itself' => ['/rewind/2023/compare/2023', null];
        yield 'a year without activities' => ['/rewind/2021/compare/2023', null];
        yield 'a counterpart without activities' => ['/rewind/2023/compare/2021', null];
    }

    public function testItMarksTheRewindSectionAsActive(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/2022');

        $this->assertStringContainsString('href="/rewind" aria-selected="true"', (string) $this->client->getResponse()->getContent());
    }

    public function testItDoesNotResolveWhenThereIsNothingToCompare(): void
    {
        $this->addActivityOneFixtures();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/all-time');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetCacheabilityCarriesTheTagsOfBothSides(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/2022');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'rewind.2023.compare.2022',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities.2022', 'activity.images.2022',
                'gear', 'settings.appearance', 'settings.general',
            ],
            explode(', ', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Tags')),
        );
    }

    public function testGetCacheabilityWhenComparedWithAllTime(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/rewind/2023/compare/all-time');

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities', 'activity.images',
                'gear', 'settings.appearance', 'settings.general',
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
