<?php

declare(strict_types=1);

namespace App\Tests\Domain\Athlete;

use App\Domain\Athlete\AthleteBirthDate;
use PHPUnit\Framework\TestCase;

class AthleteBirthDateTest extends TestCase
{
    public function testFromStringWhenInvalid(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date "invalid" set for athlete birthday in config.yaml file'));
        AthleteBirthDate::fromString('invalid');
    }
}
