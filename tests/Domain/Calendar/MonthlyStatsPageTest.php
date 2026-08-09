<?php

namespace App\Tests\Domain\Calendar;

use App\Domain\Calendar\MonthlyStatsPage;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MonthlyStatsPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private MonthlyStatsPage $monthlyStatsPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->monthlyStatsPage->render());
    }

    public function testRenderWithoutActivities(): void
    {
        $this->assertMatchesHtmlSnapshot($this->monthlyStatsPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('monthly-stats', $this->monthlyStatsPage->getPath());
        $this->assertEquals('monthly-stats', $this->monthlyStatsPage->getCacheability()->getCacheKey());
    }

    public function testGetCacheTags(): void
    {
        $this->assertEquals(
            ['settings.appearance', 'settings.general', RootCacheTag::ACTIVITIES->toTagString()],
            $this->monthlyStatsPage->getCacheability()->getCacheTags()->toTagStrings()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->monthlyStatsPage = $this->getContainer()->get(MonthlyStatsPage::class);
    }
}
