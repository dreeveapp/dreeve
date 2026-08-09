<?php

namespace App\Tests\Domain\Calendar;

use App\Domain\Calendar\MonthPageResolver;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MonthPageResolverTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private MonthPageResolver $monthPageResolver;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->monthPageResolver->resolve('month/2023-06')->render());
    }

    public function testRenderJanuary(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->monthPageResolver->resolve('month/2023-01')->render());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();

        $page = $this->monthPageResolver->resolve('month/2023-06');

        $this->assertEquals('month/2023-06', $page->getPath());
        $this->assertEquals('month.2023-06', $page->getCacheability()->getCacheKey());
    }

    public function testItIsTaggedWithTheMonthsItRenders(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            ['settings.appearance', 'settings.general', 'activities.2022-12', 'activities.2023-01', 'activities.2023-02'],
            $this->monthPageResolver->resolve('month/2023-01')->getCacheability()->getCacheTags()->toTagStrings()
        );
    }

    public function testItResolvesEveryMonthBetweenTheFirstActivityAndToday(): void
    {
        // The clock is paused on 2023-10-17, the test set starts in July 2020.
        $this->provideFullTestSet();

        $this->assertNotNull($this->monthPageResolver->resolve('month/2020-07'));
        $this->assertNotNull($this->monthPageResolver->resolve('month/2023-10'));
    }

    public function testItDoesNotResolveMonthsOutsideThatRange(): void
    {
        $this->provideFullTestSet();

        $this->assertNull($this->monthPageResolver->resolve('month/2020-06'));
        $this->assertNull($this->monthPageResolver->resolve('month/2023-11'));
    }

    public function testItDoesNotResolveMalformedPaths(): void
    {
        $this->provideFullTestSet();

        $this->assertNull($this->monthPageResolver->resolve('month/2023-13'));
        $this->assertNull($this->monthPageResolver->resolve('month/2023-6'));
        $this->assertNull($this->monthPageResolver->resolve('month/not-a-month'));
        $this->assertNull($this->monthPageResolver->resolve('month'));
        $this->assertNull($this->monthPageResolver->resolve('monthly-stats'));
    }

    public function testItDoesNotResolveWhenThereAreNoActivities(): void
    {
        $this->assertNull($this->monthPageResolver->resolve('month/2023-06'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->monthPageResolver = $this->getContainer()->get(MonthPageResolver::class);
    }
}
