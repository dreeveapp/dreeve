<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\ImportStatus;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\KeyValue\DbalKeyValueStore;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

class ImportStatusTest extends ContainerTestCase
{
    private Filesystem $filesystem;
    private DbalKeyValueStore $keyValueStore;
    private ImportStatus $importStatus;

    public function testItIsPendingWhenTheWatchDirectoryHoldsAProcessableFile(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $this->assertTrue($this->importStatus->isPending());
    }

    public function testItIsNotPendingWhenTheWatchDirectoryOnlyHoldsUnsupportedFiles(): void
    {
        $this->filesystem->write('watch/readme.txt', 'some text');

        $this->assertFalse($this->importStatus->isPending());
    }

    public function testItIsNotPendingWhenTheWatchDirectoryIsEmpty(): void
    {
        $this->filesystem->createDirectory('watch');

        $this->assertFalse($this->importStatus->isPending());
    }

    public function testItIsPendingWhenAnAutomationRulesBackfillIsQueued(): void
    {
        $this->filesystem->createDirectory('watch');
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::AUTOMATION_RULES_BACKFILL,
            value: Value::fromString(Json::encode([
                'automationRuleIds' => ['automationRule-1'],
                'activityIds' => ['activity-a'],
            ])),
        ));

        $this->assertTrue($this->importStatus->isPending());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->keyValueStore = new DbalKeyValueStore($this->getConnection());
        $this->importStatus = new ImportStatus(
            new WatchDirectory(KernelProjectDir::fromString('/project/dir'), $this->filesystem),
            new AutomationRulesBackfillQueue($this->keyValueStore),
        );
    }
}
