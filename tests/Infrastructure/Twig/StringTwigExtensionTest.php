<?php

namespace App\Tests\Infrastructure\Twig;

use App\Infrastructure\Twig\StringTwigExtension;
use PHPUnit\Framework\TestCase;

class StringTwigExtensionTest extends TestCase
{
    public function testDoEllipses(): void
    {
        $extension = new StringTwigExtension();

        $this->assertEquals(
            'test',
            $extension->doEllipses('test', 10)
        );

        $this->assertEquals(
            't...',
            $extension->doEllipses('tester', 4)
        );

        $this->assertEquals(
            'Réunion',
            $extension->doEllipses('Réunion', 7)
        );
    }

    public function testDoRepeat(): void
    {
        $extension = new StringTwigExtension();

        $this->assertEquals(
            'yyyyyyyyyy',
            $extension->doRepeat('y', 10)
        );
    }

    public function testDoCountUpperCaseChars(): void
    {
        $extension = new StringTwigExtension();

        $this->assertEquals(
            3,
            $extension->doCountUpperCaseChars('AbCdEfghi')
        );
    }
}
