<?php

namespace App\Tests\Domain\Rewind;

use App\Domain\Rewind\RewindPage;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RewindPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private RewindPage $rewindPage;

    public function testRenderForAllTime(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindPage->resolve('rewind');
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    public function testRenderForASingleYear(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindPage->resolve('rewind/2023');
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    #[DataProvider('providePathsToResolve')]
    public function testResolve(string $path, ?string $expectedPath): void
    {
        $this->provideFullTestSet();

        $this->assertEquals($expectedPath, $this->rewindPage->resolve($path)?->getPath());
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

        $page = $this->rewindPage->resolve('rewind/all-time');
        $this->assertNotNull($page);

        $this->assertEquals('rewind.all-time', $page->getCacheability()->getCacheKey());
        $this->assertEqualsCanonicalizing(
            ['activities', 'activity.images', 'gear', 'settings.appearance', 'settings.general'],
            $page->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    public function testGetCacheabilityForASingleYearIsScopedToThatYear(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindPage->resolve('rewind/2023');
        $this->assertNotNull($page);

        $this->assertEquals('rewind.2023', $page->getCacheability()->getCacheKey());
        $this->assertEqualsCanonicalizing(
            ['activities.2023', 'activity.images.2023', 'gear', 'settings.appearance', 'settings.general'],
            $page->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rewindPage = $this->getContainer()->get(RewindPage::class);
    }
}
