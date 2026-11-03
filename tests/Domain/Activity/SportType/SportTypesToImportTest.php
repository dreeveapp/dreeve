<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\SportType;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypesToImport;
use PHPUnit\Framework\TestCase;

class SportTypesToImportTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            SportTypesToImport::fromArray(SportType::cases()),
            SportTypesToImport::from([])
        );

        $this->assertEquals(
            SportTypesToImport::fromArray([SportType::WALK, SportType::RUN]),
            SportTypesToImport::from(['Walk', 'Run'])
        );
    }
}
