<?php

declare(strict_types=1);

namespace App\Tests\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\ActivityVisibilitiesToImport;
use App\Domain\Activity\ActivityVisibility;
use PHPUnit\Framework\TestCase;

class ActivityVisibilitiesToImportTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            ActivityVisibilitiesToImport::fromArray(ActivityVisibility::cases()),
            ActivityVisibilitiesToImport::from([])
        );

        $this->assertEquals(
            ActivityVisibilitiesToImport::fromArray([ActivityVisibility::ONLY_ME, ActivityVisibility::EVERYONE]),
            ActivityVisibilitiesToImport::from(['only_me', 'everyone'])
        );
    }
}
