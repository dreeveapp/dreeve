<?php

declare(strict_types=1);

namespace App\Tests\Domain\Image;

use App\Domain\Image\ImageOrientation;
use PHPUnit\Framework\TestCase;

class ImageOrientationTest extends TestCase
{
    public function testFromWidthAndHeight(): void
    {
        $this->assertEquals(
            ImageOrientation::PORTRAIT,
            ImageOrientation::fromWidthAndHeight(800, 1200)
        );
        $this->assertEquals(
            ImageOrientation::LANDSCAPE,
            ImageOrientation::fromWidthAndHeight(1200, 800)
        );
        $this->assertEquals(
            ImageOrientation::PORTRAIT,
            ImageOrientation::fromWidthAndHeight(1200, 1200)
        );
    }
}
