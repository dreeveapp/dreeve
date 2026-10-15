<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Import\FileParser\Fit\FitManufacturer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class FitManufacturerTest extends TestCase
{
    #[TestWith(data: [260, true])]
    #[TestWith(data: [1, false])]
    #[TestWith(data: [23, false])]
    #[TestWith(data: [255, false])]
    public function testHasUnreliableProductName(int $manufacturerId, bool $expected): void
    {
        $this->assertSame($expected, FitManufacturer::hasUnreliableProductName($manufacturerId));
    }
}
