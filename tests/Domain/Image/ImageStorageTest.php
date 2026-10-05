<?php

declare(strict_types=1);

namespace App\Tests\Domain\Image;

use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImagePath;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Infrastructure\ValueObject\String\Path;
use App\Tests\ContainerTestCase;
use League\Flysystem\FilesystemOperator;

class ImageStorageTest extends ContainerTestCase
{
    private ImageStorage $imageStorage;
    private FilesystemOperator $fileStorage;

    public function testItStoresImageInGivenDirectory(): void
    {
        $path = $this->imageStorage->store(
            new NewImage(Path::fromString('brakes.PNG'), 'image-content'),
            ImageDirectory::GEAR_MAINTENANCE,
        );

        $this->assertSame('files/gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png', $path->toLocalImagePath());
        $this->assertTrue($this->fileStorage->fileExists('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertSame('image-content', $this->fileStorage->read('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
    }

    public function testItStoresImageAtGivenPath(): void
    {
        $this->imageStorage->storeAt(ImagePath::fromLocalImagePath('files/activities/header.webp'), 'image-content');

        $this->assertSame('image-content', $this->fileStorage->read('activities/header.webp'));
    }

    public function testItRemovesExistingImage(): void
    {
        $this->fileStorage->write('gear/some-image.png', 'image-content');

        $this->imageStorage->remove(ImagePath::fromLocalImagePath('files/gear/some-image.png'));

        $this->assertFalse($this->fileStorage->fileExists('gear/some-image.png'));
    }

    public function testItSilentlyIgnoresRemovalOfMissingImage(): void
    {
        $this->imageStorage->remove(ImagePath::fromLocalImagePath('files/gear/does-not-exist.png'));

        $this->assertFalse($this->fileStorage->fileExists('gear/does-not-exist.png'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->imageStorage = $this->getContainer()->get(ImageStorage::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
