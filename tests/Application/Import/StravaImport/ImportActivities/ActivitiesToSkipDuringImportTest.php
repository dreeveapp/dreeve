<?php

declare(strict_types=1);

namespace App\Tests\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\ActivitiesToSkipDuringImport;
use App\Domain\Activity\ActivityId;
use PHPUnit\Framework\TestCase;

class ActivitiesToSkipDuringImportTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            ActivitiesToSkipDuringImport::empty(),
            ActivitiesToSkipDuringImport::from([])
        );

        $this->assertEquals(
            ActivitiesToSkipDuringImport::empty()->add(ActivityId::fromUnprefixed('test')),
            ActivitiesToSkipDuringImport::from(['test'])
        );
    }
}
