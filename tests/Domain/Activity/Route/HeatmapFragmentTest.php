<?php

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\HeatmapFragment;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapFragmentTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private HeatmapFragment $heatmapPage;

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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->heatmapPage = $this->getContainer()->get(HeatmapFragment::class);
    }
}
