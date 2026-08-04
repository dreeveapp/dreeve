<?php

namespace App\Tests\Domain\Activity\Image;

use App\Domain\Activity\Image\PhotosPage;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class PhotosPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private PhotosPage $photosPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->photosPage->render());
    }

    public function testGetPath(): void
    {
        $this->assertEquals('photos', $this->photosPage->getPath());
        $this->assertEquals('photos', $this->photosPage->getCacheKey());
    }

    public function testGetCacheability(): void
    {
        $cacheability = $this->photosPage->getCacheability();

        $this->assertTrue($cacheability->isCacheable());
        $this->assertEquals(
            [CacheTag::SETTINGS_APPEARANCE->value, CacheTag::SETTINGS_GENERAL->value, CacheTag::ACTIVITY_IMAGES->value],
            $cacheability->getCacheTags()->toTagStrings()
        );
        $this->assertEquals(
            [TrustedVisitorCacheContext::class],
            $cacheability->getCacheContexts()->toArray()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->photosPage = $this->getContainer()->get(PhotosPage::class);
    }
}
