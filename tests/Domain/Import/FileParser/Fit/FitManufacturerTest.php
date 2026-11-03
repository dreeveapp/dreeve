<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Import\FileParser\Fit\FitManufacturer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class FitManufacturerTest extends TestCase
{
    #[TestWith([260, true])]
    #[TestWith([1, false])]
    #[TestWith([23, false])]
    #[TestWith([255, false])]
    public function testHasUnreliableProductName(int $manufacturerId, bool $expected): void
    {
        $this->assertSame($expected, FitManufacturer::hasUnreliableProductName($manufacturerId));
    }
}
