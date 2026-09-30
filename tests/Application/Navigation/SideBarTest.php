<?php

namespace App\Tests\Application\Navigation;

use App\Application\Navigation\NavigationSection;
use App\Application\Navigation\SideBar;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class SideBarTest extends ContainerTestCase
{
    use ProvideTestData;

    private SideBar $sideBar;

    public function testRenderMarksTheActiveSectionOfEachCachedSideBar(): void
    {
        $this->provideFullTestSet();

        $this->sideBar->render(NavigationSection::ACTIVITIES);
        $render = (string) $this->sideBar->render(NavigationSection::SEGMENTS);

        $this->assertStringContainsString('href="/segments" aria-selected="true"', $render);
        $this->assertStringContainsString('href="/activities" aria-selected="false"', $render);
    }

    #[DataProvider('provideCacheTags')]
    public function testItIsCachedUntilItsCacheTagsAreInvalidated(RootCacheTag $cacheTag, bool $expectedToBeInvalidated): void
    {
        $this->provideFullTestSet();
        $renderCache = $this->getContainer()->get(RenderCache::class);
        $renderCache->clear();

        $this->sideBar->render(NavigationSection::ACTIVITIES);
        $renderCache->invalidateTags($cacheTag);

        $this->assertSame(
            !$expectedToBeInvalidated,
            $renderCache->get(
                cacheKey: 'app-shell.sidebar.activities',
                cacheability: Cacheability::for('app-shell.sidebar.activities', CacheTags::empty()),
                callback: fn (): string => 'rendered',
            )->wasServedFromCache(),
        );
    }

    public static function provideCacheTags(): iterable
    {
        foreach (RootCacheTag::cases() as $cacheTag) {
            yield $cacheTag->value => [$cacheTag, in_array($cacheTag, [
                RootCacheTag::ACTIVITIES,
                RootCacheTag::ACTIVITY_IMAGES,
                RootCacheTag::CHALLENGES,
                RootCacheTag::GEAR,
                RootCacheTag::SETTINGS_GENERAL,
                RootCacheTag::SETTINGS_APPEARANCE,
            ], true)];
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sideBar = $this->getContainer()->get(SideBar::class);
    }
}
