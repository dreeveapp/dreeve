<?php

namespace App\Tests\Domain\Challenge;

use App\Domain\Challenge\ChallengesPage;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ChallengesPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private ChallengesPage $challengesPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->challengesPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('challenges', $this->challengesPage->getPath());
        $this->assertEquals('challenges', $this->challengesPage->getCacheability()->getCacheKey());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->challengesPage = $this->getContainer()->get(ChallengesPage::class);
    }
}
