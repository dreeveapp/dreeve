<?php

namespace App\Infrastructure\Twig;

use Twig\Attribute\AsTwigFilter;

final readonly class StringTwigExtension
{
    #[AsTwigFilter('ellipses')]
    public static function doEllipses(string $string, int $maxLength): string
    {
        if (mb_strlen($string, 'UTF-8') <= $maxLength) {
            return $string;
        }

        return mb_substr($string, 0, $maxLength - 3, 'UTF-8').'...';
    }

    #[AsTwigFilter('repeat')]
    public static function doRepeat(string $char, int $times): string
    {
        return str_repeat($char, $times);
    }

    #[AsTwigFilter('countUppercaseChars')]
    public static function doCountUpperCaseChars(string $string): int
    {
        /** @var string $stringWithoutUppercaseChars */
        $stringWithoutUppercaseChars = preg_replace('/[A-Z]/', '', $string);

        return strlen($string) - strlen($stringWithoutUppercaseChars);
    }
}
