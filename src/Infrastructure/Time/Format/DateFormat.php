<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Format;

final readonly class DateFormat implements \Stringable
{
    private function __construct(
        private string $dateFormatString,
    ) {
    }

    public static function from(string $dateFormatString): self
    {
        $validChars = str_split('dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU');
        // Remove escaped characters (e.g., \Y or \\)
        /** @var non-empty-string $strippedValidChars */
        $strippedValidChars = preg_replace('/\\\\./', '', $dateFormatString);
        preg_match_all('/(.)/', $strippedValidChars, $matches);

        if (empty($matches[1])) {
            throw new \InvalidArgumentException('Invalid date format provided. Format cannot be empty');
        }

        if ($invalidChars = array_filter($matches[1], fn (string $char): bool => !in_array($char, $validChars) && ctype_alpha($char))) {
            throw new \InvalidArgumentException(sprintf('Invalid date format provided "%s", invalid format characters found: %s', $dateFormatString, implode(', ', array_unique($invalidChars))));
        }

        return new self($dateFormatString);
    }

    public function __toString(): string
    {
        return $this->dateFormatString;
    }
}
