<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\FileSystem;

use App\Infrastructure\ValueObject\String\CompressedString;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Assert;
use Spatie\Snapshots\MatchesSnapshots;

trait provideAssertFileSystem
{
    use MatchesSnapshots;

    protected function assertFileSystemWritesAreEmpty(FilesystemOperator $fileSystem): void
    {
        Assert::assertSame([], $fileSystem->listContents('/', true)->toArray(), 'fileSystem is not empty');
    }

    protected function assertFileSystemWrites(FilesystemOperator $fileSystem, bool $contentIsCompressed = false): void
    {
        foreach ($fileSystem->listContents('/', true) as $item) {
            $path = $item->path();

            if (!$item instanceof FileAttributes) {
                continue;
            }

            $content = $fileSystem->read($path);
            if ($contentIsCompressed) {
                $content = CompressedString::fromCompressed($content)->uncompress();
            }
            if (str_ends_with($path, '.json')) {
                $this->assertMatchesJsonSnapshot($content);
                continue;
            }
            if (str_ends_with($path, '.html')) {
                $this->assertMatchesHtmlSnapshot($content);
                continue;
            }
            if (str_ends_with($path, '.gpx') || str_ends_with($path, '.svg')) {
                $this->assertMatchesXmlSnapshot($content);
                continue;
            }
            $this->assertMatchesTextSnapshot($content);
        }
    }
}
