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

    #[TestWith([1, true])]
    #[TestWith([13, true])]
    #[TestWith([15, true])]
    #[TestWith([89, true])]
    #[TestWith([263, true])]
    #[TestWith([0, false])]
    #[TestWith([23, false])]
    #[TestWith([32, false])]
    #[TestWith([123, false])]
    #[TestWith([294, false])]
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
