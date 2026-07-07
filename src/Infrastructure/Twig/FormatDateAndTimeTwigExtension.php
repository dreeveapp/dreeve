<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Time\Format\TimeFormat;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Twig\Attribute\AsTwigFilter;

final readonly class FormatDateAndTimeTwigExtension
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[AsTwigFilter('formatDate')]
    public function formatDate(SerializableDateTime $date, string $formatType = 'short'): string
    {
        $dateAndTimeFormat = $this->settingsRepository->appearance()->getDateAndTimeFormat();
        $dateFormat = match ($formatType) {
            'short' => $dateAndTimeFormat->getDateFormatShort(),
            'normal' => $dateAndTimeFormat->getDateFormatNormal(),
            default => throw new \InvalidArgumentException(sprintf('Invalid date formatType "%s"', $formatType)),
        };

        return $date->translatedFormat((string) $dateFormat);
    }

    #[AsTwigFilter('formatTime')]
    public function formatTime(SerializableDateTime $date, bool $includeSeconds = false): string
    {
        $timeFormat = $this->settingsRepository->appearance()->getDateAndTimeFormat()->getTimeFormat();

        if ($includeSeconds) {
            return match ($timeFormat) {
                TimeFormat::TWENTY_FOUR => $date->format('H:i:s'),
                TimeFormat::AM_PM => $date->format('h:i:s a'),
            };
        }

        return match ($timeFormat) {
            TimeFormat::TWENTY_FOUR => $date->format('H:i'),
            TimeFormat::AM_PM => $date->format('h:i a'),
        };
    }
}
