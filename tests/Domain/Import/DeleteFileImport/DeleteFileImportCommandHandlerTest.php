<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\DeleteFileImport;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Import\DeleteFileImport\DeleteFileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Import\FileImportBuilder;

class DeleteFileImportCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private FileImportRepository $fileImportRepository;
    private RenderCache $renderCache;

    public function testHandle(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->build()
        );

        $this->commandBus->dispatch(DeleteFileImport::fromPayload([
            'fileImportId' => (string) FileImportId::fromUnprefixed('1'),
        ]));

        $this->assertSame(
            [(string) FileImportId::fromUnprefixed('2')],
            $this->getConnection()->executeQuery('SELECT fileImportId FROM FileImport')->fetchFirstColumn(),
        );
    }

    public function testItInvalidatesTheCachedPageOfTheLinkedActivity(): void
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

        $this->commandBus->dispatch(DeleteFileImport::fromPayload([
            'fileImportId' => (string) FileImportId::fromUnprefixed('1'),
        ]));

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

    public function testItIgnoresAnUnknownFileImport(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->build()
        );

        $this->commandBus->dispatch(DeleteFileImport::fromPayload([
            'fileImportId' => (string) FileImportId::fromUnprefixed('nope'),
        ]));

        $this->assertSame(
            [(string) FileImportId::fromUnprefixed('1')],
            $this->getConnection()->executeQuery('SELECT fileImportId FROM FileImport')->fetchFirstColumn(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
    }
}
