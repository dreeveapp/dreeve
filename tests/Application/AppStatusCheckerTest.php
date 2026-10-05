<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\FileSystem\UnwritablePermissionChecker;
use PHPUnit\Framework\Attributes\TestWith;

class AppStatusCheckerTest extends ContainerTestCase
{
    #[TestWith(['ensureIsReadyForStravaImport'])]
    #[TestWith(['ensureIsReadyForFileImport'])]
    public function testItIsReadyWhenTheFileSystemIsWritable(string $method): void
    {
        $this->expectNotToPerformAssertions();

        new AppStatusChecker(new SuccessfulPermissionChecker())->$method();
    }

    #[TestWith(['ensureIsReadyForStravaImport'])]
    #[TestWith(['ensureIsReadyForFileImport'])]
    public function testItThrowsWhenTheFileSystemIsNotWritable(string $method): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseFileSystemIsNotWritable());

        new AppStatusChecker(new UnwritablePermissionChecker())->$method();
    }
}
