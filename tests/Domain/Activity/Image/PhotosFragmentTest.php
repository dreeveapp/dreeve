<?php

namespace App\Tests\Domain\Activity\Image;

use App\Domain\Activity\Image\PhotosFragment;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class PhotosFragmentTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private PhotosFragment $photosPage;

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

        $this->photosPage = $this->getContainer()->get(PhotosFragment::class);
    }
}
