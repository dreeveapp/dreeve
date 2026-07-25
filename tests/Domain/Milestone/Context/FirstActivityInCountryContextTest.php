<?php

namespace App\Tests\Domain\Milestone\Context;

use App\Domain\Milestone\Context\FirstActivityInCountryContext;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class FirstActivityInCountryContextTest extends TestCase
{
    public function testGetters(): void
    {
        $context = new FirstActivityInCountryContext('be', 'Morning ride');

        $this->assertEquals('Belgium', $context->getCountryName());
        $this->assertEquals('be', $context->getCountryCode());
        $this->assertEquals('Morning ride', $context->getActivityName());
    }

    #[TestWith(data: ['xk'])]
    #[TestWith(data: ['XK'])]
    public function testGetCountryNameForKosovo(string $countryCode): void
    {
        $context = new FirstActivityInCountryContext($countryCode, 'Ride in Kosovo');

        $this->assertEquals('Kosovo', $context->getCountryName());
    }
}
