<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Measurement\Imperial;
use App\Infrastructure\ValueObject\Measurement\Metric;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Measurement\Unit;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Measurement\Velocity\Pace;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class MeasurementTwigExtension
{
    use ProvideTimeFormats;

    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[AsTwigFilter('convertMeasurement')]
    public function convertMeasurement(Unit $measurement): Unit
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        if (UnitSystem::IMPERIAL === $unitSystem && $measurement instanceof Metric) {
            return $measurement->toImperial();
        }
        if (UnitSystem::METRIC === $unitSystem && $measurement instanceof Imperial) {
            return $measurement->toMetric();
        }

        return $measurement;
    }

    #[AsTwigFilter('formatMeasurement')]
    public function formatMeasurement(Unit $measurement, int $precision): string
    {
        $convertedMeasurement = $this->convertMeasurement($measurement);
        $measurementInScalar = $convertedMeasurement->toFloat();

        return self::formatNumber($measurementInScalar, $precision);
    }

    #[AsTwigFilter('renderMeasurement')]
    public function renderMeasurement(Unit $measurement, int $precision, ?string $symbolSuffix = null): string
    {
        $convertedMeasurement = $this->convertMeasurement($measurement);
        $measurementInScalar = $convertedMeasurement->toFloat();
        $formattedNumber = self::formatNumber($measurementInScalar, $precision);

        if (!$symbolSuffix) {
            if ('' === $convertedMeasurement->getSymbol()) {
                return $formattedNumber;
            }

            return sprintf(
                '%s<span class="text-xxs ml-px whitespace-nowrap">%s</span>',
                $formattedNumber,
                $convertedMeasurement->getSymbol()
            );
        }

        return sprintf(
            '%s<span class="text-xxs ml-px">%s %s</span>',
            $formattedNumber,
            $convertedMeasurement->getSymbol(),
            $symbolSuffix
        );
    }

    #[AsTwigFilter('formatPace')]
    public function formatPace(Pace $pace): string
    {
        $pace = $pace->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem());

        return $this->formatDurationAsClock($pace->toInt());
    }

    #[AsTwigFunction('renderUnitSymbol')]
    public function getUnitSymbol(string $unitName): string
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        return match ($unitName) {
            'distance' => $unitSystem->distanceSymbol(),
            'elevation' => $unitSystem->elevationSymbol(),
            'pace' => $unitSystem->paceSymbol(),
            'speed' => $unitSystem->speedSymbol(),
            default => throw new \RuntimeException(sprintf('Invalid unitName "%s"', $unitName)),
        };
    }

    #[AsTwigFilter('formatNumber')]
    public function formatNumber(?float $number, int $precision): string
    {
        if (is_null($number)) {
            return '0';
        }

        $precision = $number < 100 ? $precision : 0;

        return number_format(round($number, $precision), $precision, '.', "\u{00A0}");
    }

    #[AsTwigFilter('formatSeconds')]
    public function formatSeconds(Seconds $seconds): string
    {
        return $this->formatDurationAsHumanString($seconds->toInt());
    }

    #[AsTwigFilter('formatSecondsAsPaddedClock')]
    public function formatSecondsAsPaddedClock(Seconds $seconds): string
    {
        return $this->formatDurationAsPaddedClock($seconds->toInt());
    }
}
