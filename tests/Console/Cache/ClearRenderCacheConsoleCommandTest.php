<?php

namespace App\Tests\Console\Cache;

use App\Application\AppVersion;
use App\Console\Cache\ClearRenderCacheConsoleCommand;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\Render;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\Console\ConsoleCommandTestCase;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ClearRenderCacheConsoleCommandTest extends ConsoleCommandTestCase
{
    private const string VERSION_MARKER_PATH = 'render-cache.version';

    private RenderCache $renderCache;
    private FilesystemOperator $buildStorage;

    public function testExecute(): void
    {
        $this->renderARenderCachedPage('first');

        $commandTester = $this->execute();

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderARenderCachedPage('second')->getContent());
        $this->assertEquals(AppVersion::getSemanticVersion(), $this->buildStorage->read(self::VERSION_MARKER_PATH));
    }

    public function testExecuteClearsEvenWhenTheMarkerMatches(): void
    {
        $this->buildStorage->write(self::VERSION_MARKER_PATH, AppVersion::getSemanticVersion());
        $this->renderARenderCachedPage('first');

        $commandTester = $this->execute();

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderARenderCachedPage('second')->getContent());
    }

    public function testExecuteWhenTheCacheIsAlreadyEmpty(): void
    {
        $commandTester = $this->execute();

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
    }

    public function testExecuteInvalidatesTaggedRendersToo(): void
    {
        $this->renderARenderCachedPage('first');

        $this->execute();
        $this->renderARenderCachedPage('second');
        $this->renderCache->invalidateTags(RootCacheTag::ACTIVITIES);

        $this->assertEquals('third', $this->renderARenderCachedPage('third')->getContent());
    }

    public function testExecuteWithVersionCheckWhenThereIsNoMarkerYet(): void
    {
        $this->renderARenderCachedPage('first');

        $commandTester = $this->execute(withVersionCheck: true);

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderARenderCachedPage('second')->getContent());
        $this->assertEquals(AppVersion::getSemanticVersion(), $this->buildStorage->read(self::VERSION_MARKER_PATH));
    }

    public function testExecuteWithVersionCheckWhenTheMarkerIsAnotherVersion(): void
    {
        $this->buildStorage->write(self::VERSION_MARKER_PATH, 'v0.0.1');
        $this->renderARenderCachedPage('first');

        $commandTester = $this->execute(withVersionCheck: true);

        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());
        $this->assertEquals('second', $this->renderARenderCachedPage('second')->getContent());
        $this->assertEquals(AppVersion::getSemanticVersion(), $this->buildStorage->read(self::VERSION_MARKER_PATH));
    }

    public function testExecuteWithVersionCheckLeavesTheCacheAloneWhenTheMarkerMatches(): void
    {
        $this->buildStorage->write(self::VERSION_MARKER_PATH, AppVersion::getSemanticVersion());
        $this->renderARenderCachedPage('first');

        $commandTester = $this->execute(withVersionCheck: true);

        $this->assertStringContainsString('matches the current app version', $commandTester->getDisplay());
        $this->assertEquals('first', $this->renderARenderCachedPage('second')->getContent());
    }

    public function testExecuteWithVersionCheckIsANoOpTheSecondTimeItRuns(): void
    {
        $commandTester = $this->execute(withVersionCheck: true);
        $this->assertStringContainsString('Cleared the render cache', $commandTester->getDisplay());

        $commandTester = $this->execute(withVersionCheck: true);
        $this->assertStringContainsString('matches the current app version', $commandTester->getDisplay());
    }

    private function execute(bool $withVersionCheck = false): CommandTester
    {
        $command = $this->getCommandInApplication(ClearRenderCacheConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            ...$withVersionCheck ? ['--'.ClearRenderCacheConsoleCommand::WITH_VERSION_CHECK_OPTION => true] : [],
        ]);

        return $commandTester;
    }

    private function renderARenderCachedPage(string $content): Render
    {
        return $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => $content,
        );
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
        return new ClearRenderCacheConsoleCommand(
            $this->renderCache,
            $this->buildStorage
        );
    }
}
