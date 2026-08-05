<?php

namespace App\Tests\Domain\Activity\Eddington;

use App\Domain\Activity\Eddington\EddingtonPage;
use App\Infrastructure\Cache\CacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class EddingtonPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private EddingtonPage $eddingtonPage;

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

    public function testGetCacheability(): void
    {
        $cacheability = $this->eddingtonPage->getCacheability();

        $this->assertTrue($cacheability->isCacheable());
        $this->assertEquals(
            [
                CacheTag::SETTINGS_APPEARANCE->value,
                CacheTag::SETTINGS_GENERAL->value,
                CacheTag::ACTIVITIES->value,
                CacheTag::SETTINGS_METRICS->value,
            ],
            $cacheability->getCacheTags()->toTagStrings()
        );
        $this->assertEmpty($cacheability->getCacheContexts()->toArray());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->eddingtonPage = $this->getContainer()->get(EddingtonPage::class);
    }
}
