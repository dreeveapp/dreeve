<?php

namespace App\Tests\Domain\Activity\Image;

use App\Domain\Activity\Image\PhotosPage;
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
        $this->assertEquals('photos', $this->photosPage->getCacheability()->getCacheKey());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->photosPage = $this->getContainer()->get(PhotosPage::class);
    }
}
