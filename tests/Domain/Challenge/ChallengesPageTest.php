<?php

namespace App\Tests\Domain\Challenge;

use App\Domain\Challenge\ChallengesPage;
use App\Infrastructure\Cache\CacheTag;
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

    public function testGetCacheability(): void
    {
        $cacheability = $this->challengesPage->getCacheability();

        $this->assertTrue($cacheability->isCacheable());
        $this->assertEquals(
            [CacheTag::SETTINGS_APPEARANCE->value, CacheTag::SETTINGS_GENERAL->value, CacheTag::CHALLENGES->value],
            $cacheability->getCacheTags()->toTagStrings()
        );
        $this->assertEmpty($cacheability->getCacheContexts()->toArray());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->challengesPage = $this->getContainer()->get(ChallengesPage::class);
    }
}
