<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media\Metadata;

use App\Domain\Media\Metadata\ExifOrientation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExifOrientationTest extends TestCase
{
    #[DataProvider('provideOrientations')]
    public function testItDescribesHowToShowTheImageUpright(
        ExifOrientation $exifOrientation,
        int $expectedClockwiseRotationInDegrees,
        bool $expectedMirrorsHorizontally,
        bool $expectedMirrorsVertically,
        bool $expectedSwapsWidthAndHeight,
    ): void {
        $this->assertSame($expectedClockwiseRotationInDegrees, $exifOrientation->clockwiseRotationInDegrees());
        $this->assertSame($expectedMirrorsHorizontally, $exifOrientation->mirrorsHorizontally());
        $this->assertSame($expectedMirrorsVertically, $exifOrientation->mirrorsVertically());
        $this->assertSame($expectedSwapsWidthAndHeight, $exifOrientation->swapsWidthAndHeight());
    }

    /**
     * @return iterable<string, array{ExifOrientation, int, bool, bool, bool}>
     */
    public static function provideOrientations(): iterable
    {
        yield 'normal' => [ExifOrientation::NORMAL, 0, false, false, false];
        yield 'mirror horizontal' => [ExifOrientation::MIRROR_HORIZONTAL, 0, true, false, false];
        yield 'rotate 180' => [ExifOrientation::ROTATE_180, 180, false, false, false];
        yield 'mirror vertical' => [ExifOrientation::MIRROR_VERTICAL, 0, false, true, false];
        yield 'transpose' => [ExifOrientation::TRANSPOSE, 90, true, false, true];
        yield 'rotate 90 clockwise' => [ExifOrientation::ROTATE_90_CW, 90, false, false, true];
        yield 'transverse' => [ExifOrientation::TRANSVERSE, 90, false, true, true];
        yield 'rotate 90 counter-clockwise' => [ExifOrientation::ROTATE_90_CCW, 270, false, false, true];
    }
}
