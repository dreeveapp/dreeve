<?php

namespace App\Tests\Domain\Rewind;

use App\Domain\Rewind\RewindComparePageResolver;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RewindComparePageResolverTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private RewindComparePageResolver $rewindComparePageResolver;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindComparePageResolver->resolve('rewind/2023/compare/2022');
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    #[DataProvider('providePathsToResolve')]
    public function testResolve(string $path, ?string $expectedPath): void
    {
        $this->provideFullTestSet();

        $this->assertEquals($expectedPath, $this->rewindComparePageResolver->resolve($path)?->getPath());
    }

    public static function providePathsToResolve(): \Generator
    {
        yield 'two years' => ['rewind/2023/compare/2022', 'rewind/2023/compare/2022'];
        yield 'a year and all time' => ['rewind/2023/compare/all-time', 'rewind/2023/compare/all-time'];
        yield 'without a counterpart falls back to the first option' => ['rewind/2023/compare', 'rewind/2023/compare/all-time'];
        yield 'all time without a counterpart falls back to the second option' => ['rewind/all-time/compare', 'rewind/all-time/compare/2023'];
        yield 'comparing an option with itself' => ['rewind/2023/compare/2023', null];
        yield 'a year without activities' => ['rewind/2021/compare/2023', null];
        yield 'a counterpart without activities' => ['rewind/2023/compare/2021', null];
        yield 'a single rewind is served by another page' => ['rewind/2023', null];
        yield 'another page entirely' => ['milestones', null];
    }

    public function testItDoesNotResolveWhenThereIsNothingToCompare(): void
    {
        $this->addActivityOneFixtures();

        $this->assertNull($this->rewindComparePageResolver->resolve('rewind/2023/compare/all-time'));
    }

    public function testGetCacheabilityCarriesTheTagsOfBothSides(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindComparePageResolver->resolve('rewind/2023/compare/2022');
        $this->assertNotNull($page);

        $this->assertEquals('rewind.2023.compare.2022', $page->getCacheability()->getCacheKey());
        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities.2022', 'activity.images.2022',
                'gear', 'settings.appearance', 'settings.general',
            ],
            $page->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    public function testGetCacheabilityWhenComparedWithAllTime(): void
    {
        $this->provideFullTestSet();

        $page = $this->rewindComparePageResolver->resolve('rewind/2023/compare/all-time');
        $this->assertNotNull($page);

        $this->assertEqualsCanonicalizing(
            [
                'activities.2023', 'activity.images.2023',
                'activities', 'activity.images',
                'gear', 'settings.appearance', 'settings.general',
            ],
            $page->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rewindComparePageResolver = $this->getContainer()->get(RewindComparePageResolver::class);
    }
}
