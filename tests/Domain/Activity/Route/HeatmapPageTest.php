<?php

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\HeatmapPage;
use App\Infrastructure\Cache\CacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private HeatmapPage $heatmapPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->heatmapPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('heatmap', $this->heatmapPage->getPath());
        $this->assertEquals('heatmap', $this->heatmapPage->getCacheability()->getCacheKey());
    }

    public function testGetCacheability(): void
    {
        $cacheability = $this->heatmapPage->getCacheability();

        $this->assertTrue($cacheability->isCacheable());
        $this->assertEquals(
            [
                CacheTag::SETTINGS_APPEARANCE->value,
                CacheTag::SETTINGS_GENERAL->value,
                CacheTag::ACTIVITY_ROUTE->value,
                CacheTag::SETTINGS_MAPS->value,
            ],
            $cacheability->getCacheTags()->toTagStrings()
        );
        $this->assertEmpty($cacheability->getCacheContexts()->toArray());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->heatmapPage = $this->getContainer()->get(HeatmapPage::class);
    }
}
