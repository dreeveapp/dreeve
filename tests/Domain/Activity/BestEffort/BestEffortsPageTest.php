<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\BestEffort\BestEffortsPage;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class BestEffortsPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private BestEffortsPage $bestEffortsPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->bestEffortsPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('best-efforts', $this->bestEffortsPage->getPath());
        $this->assertEquals('best-efforts', $this->bestEffortsPage->getCacheability()->getCacheKey());
    }

    public function testItShouldExpireAtMidnight(): void
    {
        // The clock is paused on 2023-10-17 16:15:04.
        $this->assertEquals(27896, $this->bestEffortsPage->getCacheability()->getTtlInSeconds());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestEffortsPage = $this->getContainer()->get(BestEffortsPage::class);
    }
}
