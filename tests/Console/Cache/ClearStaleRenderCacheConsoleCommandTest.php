<?php

namespace App\Tests\Console\Cache;

use App\Application\AppVersion;
use App\Console\Cache\ClearStaleRenderCacheConsoleCommand;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\Console\ConsoleCommandTestCase;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ClearStaleRenderCacheConsoleCommandTest extends ConsoleCommandTestCase
{
    private const string VERSION_MARKER_PATH = 'render-cache.version';

    private RenderCache $renderCache;
    private FilesystemOperator $buildStorage;

    public function testExecuteWhenThereIsNoMarkerYet(): void
    {
        $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'first',
        );

        $command = $this->getCommandInApplication(ClearStaleRenderCacheConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'second',
        )->getContent());
        $this->assertEquals(AppVersion::getSemanticVersion(), $this->buildStorage->read(self::VERSION_MARKER_PATH));
    }

    public function testExecuteWhenTheMarkerIsAnotherVersion(): void
    {
        $this->buildStorage->write(self::VERSION_MARKER_PATH, 'v0.0.1');
        $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'first',
        );

        $command = $this->getCommandInApplication(ClearStaleRenderCacheConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'second',
        )->getContent());
        $this->assertEquals(AppVersion::getSemanticVersion(), $this->buildStorage->read(self::VERSION_MARKER_PATH));
    }

    public function testExecuteLeavesTheCacheAloneWhenTheMarkerMatches(): void
    {
        $this->buildStorage->write(self::VERSION_MARKER_PATH, AppVersion::getSemanticVersion());
        $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'first',
        );

        $command = $this->getCommandInApplication(ClearStaleRenderCacheConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('matches the current app version', $commandTester->getDisplay());
        $this->assertEquals('first', $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'second',
        )->getContent());
    }

    public function testExecuteIsANoOpTheSecondTimeItRuns(): void
    {
        $command = $this->getCommandInApplication(ClearStaleRenderCacheConsoleCommand::NAME);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['command' => $command->getName()]);
        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());

        $commandTester->execute(['command' => $command->getName()]);
        $this->assertStringContainsString('matches the current app version', $commandTester->getDisplay());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
        $this->buildStorage = $this->getContainer()->get('build.storage');
        $this->buildStorage->deleteDirectory('/');
    }

    #[\Override]
    protected function getConsoleCommand(): Command
    {
        return new ClearStaleRenderCacheConsoleCommand(
            $this->renderCache,
            $this->buildStorage
        );
    }
}
