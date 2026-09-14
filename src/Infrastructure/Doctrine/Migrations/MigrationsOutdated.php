<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

final class MigrationsOutdated extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Database migrations have been squashed. You need to update to version v5.3.3 before upgrading this version. '
            .'Please pull v5.3.3 of the app, run the import there, then update to this version again.'
        );
    }
}
