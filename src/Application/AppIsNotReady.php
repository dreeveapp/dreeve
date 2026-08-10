<?php

declare(strict_types=1);

namespace App\Application;

final class AppIsNotReady extends \RuntimeException
{
    public static function becauseFileSystemIsNotWritable(): self
    {
        return new self('Make sure the container has write permissions to "storage/database" and "storage/files" on the host system');
    }
}
