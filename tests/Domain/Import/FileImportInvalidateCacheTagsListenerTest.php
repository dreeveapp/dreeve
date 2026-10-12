<?php

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Tests\ContainerTestCase;

class FileImportInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private FileImportRepository $fileImportRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesTheLinkedActivityWhenAFileImportIsDeleted(): void
    {
        foreach (['1', '2'] as $id) {
            $this->fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed($id))
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->build()
            );
            $this->renderCache->get(
                cacheKey: 'activity-'.$id,
                cacheability: Cacheability::for('stub', CacheTags::of(ActivityCacheTag::for(ActivityId::fromUnprefixed($id)))),
                callback: fn (): string => 'rendered',
            );
        }

        $fileImport = $this->fileImportRepository->find(FileImportId::fromUnprefixed('1'));
        $fileImport->delete();
        $this->fileImportRepository->delete($fileImport);

        foreach (['1' => false, '2' => true] as $id => $servedFromCache) {
            $this->assertSame(
                $servedFromCache,
                $this->renderCache->get(
                    cacheKey: 'activity-'.$id,
                    cacheability: Cacheability::for('stub', CacheTags::of(ActivityCacheTag::for(ActivityId::fromUnprefixed((string) $id)))),
                    callback: fn (): string => 'rendered',
                )->wasServedFromCache()
            );
        }
    }

    public function testItInvalidatesNothingWhenTheDeletedFileImportHasNoActivity(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withActivityId(null)
                ->build()
        );
        $this->renderCache->get(
            cacheKey: 'activity-1',
            cacheability: Cacheability::for('stub', CacheTags::of(ActivityCacheTag::for(ActivityId::fromUnprefixed('1')))),
            callback: fn (): string => 'rendered',
        );

        $fileImport = $this->fileImportRepository->find(FileImportId::fromUnprefixed('1'));
        $fileImport->delete();
        $this->fileImportRepository->delete($fileImport);

        $this->assertTrue(
            $this->renderCache->get(
                cacheKey: 'activity-1',
                cacheability: Cacheability::for('stub', CacheTags::of(ActivityCacheTag::for(ActivityId::fromUnprefixed('1')))),
                callback: fn (): string => 'rendered',
            )->wasServedFromCache()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
    }
}
