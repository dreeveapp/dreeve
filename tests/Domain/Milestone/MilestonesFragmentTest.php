<?php

namespace App\Tests\Domain\Milestone;

use App\Domain\Milestone\MilestonesFragment;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MilestonesFragmentTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private MilestonesFragment $milestonesPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->milestonesPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('milestones', $this->milestonesPage->getPath());
        $this->assertEquals('milestones', $this->milestonesPage->getCacheability()->getCacheKey());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->milestonesPage = $this->getContainer()->get(MilestonesFragment::class);
    }
}
