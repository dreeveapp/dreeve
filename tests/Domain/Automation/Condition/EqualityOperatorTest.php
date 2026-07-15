<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\EqualityOperator;
use PHPUnit\Framework\TestCase;

class EqualityOperatorTest extends TestCase
{
    public function testAppliesTo(): void
    {
        $this->assertTrue(EqualityOperator::IS->isSatisfiedBy(true));
        $this->assertFalse(EqualityOperator::IS->isSatisfiedBy(false));
        $this->assertFalse(EqualityOperator::IS_NOT->isSatisfiedBy(true));
        $this->assertTrue(EqualityOperator::IS_NOT->isSatisfiedBy(false));
    }
}
