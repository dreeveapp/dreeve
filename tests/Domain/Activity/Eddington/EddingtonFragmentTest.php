<?php

namespace App\Tests\Domain\Activity\Eddington;

use App\Domain\Activity\Eddington\EddingtonFragment;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class EddingtonFragmentTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private EddingtonFragment $eddingtonPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->eddingtonPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('eddington', $this->eddingtonPage->getPath());
        $this->assertEquals('eddington', $this->eddingtonPage->getCacheability()->getCacheKey());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->eddingtonPage = $this->getContainer()->get(EddingtonFragment::class);
    }
}
