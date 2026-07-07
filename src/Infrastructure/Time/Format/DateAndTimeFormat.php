<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Format;

final readonly class DateAndTimeFormat
{
    private function __construct(
        private DateFormat $dateFormatShort,
        private DateFormat $dateFormatNormal,
        private TimeFormat $timeFormat,
    ) {
    }

    public static function create(
        string $dateFormatShort,
        string $dateFormatNormal,
        int $timeFormat,
    ): self {
        return new self(
            dateFormatShort: DateFormat::from($dateFormatShort),
            dateFormatNormal: DateFormat::from($dateFormatNormal),
            timeFormat: TimeFormat::from($timeFormat)
        );
    }

    public function getDateFormatShort(): DateFormat
    {
        return $this->dateFormatShort;
    }

    public function getDateFormatNormal(): DateFormat
    {
        return $this->dateFormatNormal;
    }

    public function getTimeFormat(): TimeFormat
    {
        return $this->timeFormat;
    }
}
