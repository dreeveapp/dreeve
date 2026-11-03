<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config\Photos;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Config\Photos\HidePhotosForSportTypes;
use PHPUnit\Framework\TestCase;

class HidePhotosForSportTypesTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            HidePhotosForSportTypes::fromArray([SportType::RIDE]),
            HidePhotosForSportTypes::from(['Ride'])
        );
    }

    public function testFromEmpty(): void
    {
        $this->assertEquals(
            HidePhotosForSportTypes::empty(),
            HidePhotosForSportTypes::from([])
        );
    }
}
