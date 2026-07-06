<?php

namespace App\Tests\Application\Build\BuildManifest;

use App\Application\Build\BuildManifest\BuildManifest;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\FileSystem\provideAssertFileSystem;
use App\Tests\ProvideTestData;

class BuildManifestCommandHandlerTest extends ContainerTestCase
{
    use ProvideTestData;
    use provideAssertFileSystem;

    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(new BuildManifest());
        $this->assertFileSystemWrites($this->getContainer()->get('build.storage'));
    }

    public function testThatManifestContainsPlaceholders(): void
    {
        $manifestContents = file_get_contents($this->getContainer()->get(KernelProjectDir::class).'/templates/manifest.json');

        $this->assertStringContainsString(
            '[APP_HOST]',
            $manifestContents,
            'The manifest.json file should contain the [APP_HOST] placeholder.'
        );
        $this->assertStringContainsString(
            '[APP_NAME]',
            $manifestContents,
            'The manifest.json file should contain the [APP_NAME] placeholder.'
        );
        $this->assertStringContainsString(
            '[APP_SHORT_NAME]',
            $manifestContents,
            'The manifest.json file should contain the [APP_SHORT_NAME] placeholder.'
        );
        $this->assertStringContainsString(
            '[APP_BASE_PATH]',
            $manifestContents,
            'The manifest.json file should contain the [APP_BASE_PATH] placeholder.'
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
