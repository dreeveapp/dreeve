<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\Pace;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class MeasurementTwigExtension
{
    use ProvideMeasurementFormats {
        formatNumber as private formatNumberFromTrait;
    }
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

        return $this->formatNumber($measurementInScalar, $precision);
    }

    #[AsTwigFilter('renderMeasurement')]
    public function renderMeasurement(Unit $measurement, int $precision, ?string $symbolSuffix = null): string
    {
        $convertedMeasurement = $this->convertMeasurement($measurement);
        $measurementInScalar = $convertedMeasurement->toFloat();
        $formattedNumber = $this->formatNumber($measurementInScalar, $precision);

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
            'proximity' => $unitSystem->proximitySymbol(),
            'pace' => $unitSystem->paceSymbol(),
            'speed' => $unitSystem->speedSymbol(),
            default => throw new \RuntimeException(sprintf('Invalid unitName "%s"', $unitName)),
        };
    }

    #[AsTwigFilter('formatNumber')]
    public function formatNumber(?float $number, int $precision): string
    {
        return $this->formatNumberFromTrait($number, $precision);
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
