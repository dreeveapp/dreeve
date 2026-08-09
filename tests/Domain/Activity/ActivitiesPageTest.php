<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivitiesPage;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivitiesPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private ActivitiesPage $activitiesPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->activitiesPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('activities', $this->activitiesPage->getPath());
        $this->assertEquals('activities', $this->activitiesPage->getCacheability()->getCacheKey());
    }

    public function testItIsTaggedWithTheActivitiesAndGearItRenders(): void
    {
        $this->assertEquals(
            ['settings.appearance', 'settings.general', 'activities', 'gear'],
            $this->activitiesPage->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activitiesPage = $this->getContainer()->get(ActivitiesPage::class);
    }
}
