<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Import\FileParser\Fit\FitProduct;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class FitProductTest extends TestCase
{
    use MatchesSnapshots;

    public function testItResolvesEveryKnownProduct(): void
    {
        $reflection = new \ReflectionClass(FitProduct::class);
        /** @var array<int, string> $garmin */
        $garmin = $reflection->getConstant('GARMIN');
        /** @var array<int, string> $favero */
        $favero = $reflection->getConstant('FAVERO');

        $names = ['garmin' => [], 'favero' => []];
        foreach (array_keys($garmin) as $productId) {
            $names['garmin'][$productId] = FitProduct::name(1, $productId);
        }
        foreach (array_keys($favero) as $productId) {
            $names['favero'][$productId] = FitProduct::name(263, $productId);
        }

        $this->assertMatchesJsonSnapshot(Json::encode($names));
    }

    #[TestWith(data: [1, true])]
    #[TestWith(data: [13, true])]
    #[TestWith(data: [15, true])]
    #[TestWith(data: [89, true])]
    #[TestWith(data: [263, true])]
    #[TestWith(data: [0, false])]
    #[TestWith(data: [23, false])]
    #[TestWith(data: [32, false])]
    #[TestWith(data: [123, false])]
    #[TestWith(data: [294, false])]
    public function testItSupportsOnlyGarminFamilyAndFavero(int $manufacturerId, bool $expectedIsSupported): void
    {
        $this->assertEquals($expectedIsSupported, FitProduct::supports($manufacturerId));
    }

    public function testItReturnsNullForUnsupportedManufacturer(): void
    {
        // Suunto (23) has no product enum, even for a product id we would otherwise know.
        $this->assertNull(FitProduct::name(23, 3121));
    }

    public function testItReturnsNullForUnknownProductId(): void
    {
        $this->assertNull(FitProduct::name(1, 99999999));
    }
}
