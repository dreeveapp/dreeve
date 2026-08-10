<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\FileSystem\UnwritablePermissionChecker;

class AppStatusCheckerTest extends ContainerTestCase
{
    public function testEnsureIsReadyForStravaImportPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new AppStatusChecker(new SuccessfulPermissionChecker())->ensureIsReadyForStravaImport();
    }

    public function testEnsureIsReadyForStravaImportThrowsWhenFileSystemIsNotWritable(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseFileSystemIsNotWritable());

        new AppStatusChecker(new UnwritablePermissionChecker())->ensureIsReadyForStravaImport();
    }

    public function testEnsureIsReadyForFileImportPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new AppStatusChecker(new SuccessfulPermissionChecker())->ensureIsReadyForFileImport();
    }

    public function testEnsureIsReadyForFileImportThrowsWhenFileSystemIsNotWritable(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseFileSystemIsNotWritable());

        new AppStatusChecker(new UnwritablePermissionChecker())->ensureIsReadyForFileImport();
    }
}
