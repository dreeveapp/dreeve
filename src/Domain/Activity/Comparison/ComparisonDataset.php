<?php

declare(strict_types=1);

namespace App\Domain\Activity\Comparison;

use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ComparisonDataset
{
    private const array PREFERRED_PRIMARY = [ComparisonMetric::MOVING_TIME, ComparisonMetric::AVERAGE_SPEED];
    private const array PREFERRED_SECONDARY = [ComparisonMetric::NORMALIZED_POWER, ComparisonMetric::AVERAGE_POWER, ComparisonMetric::AVERAGE_HEART_RATE];

    private function __construct(
        private ComparedActivities $comparedActivities,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        ComparedActivities $comparedActivities,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            comparedActivities: $comparedActivities,
            unitSystem: $unitSystem,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $available = [];
        $metrics = [];

        foreach (ComparisonMetric::cases() as $metric) {
            $available[$metric->value] = $this->comparedActivities->hasValuesFor($metric, $this->unitSystem);
            $metrics[] = [
                'key' => $metric->value,
                'label' => $metric->trans($this->translator),
                'unit' => $metric->getUnitSymbol($this->unitSystem),
                'direction' => $metric->getDirection()->value,
                'formatter' => $metric->getChartValueFormatter(),
                'available' => $available[$metric->value],
            ];
        }

        $rows = [];
        foreach ($this->comparedActivities as $comparedActivity) {
            $values = [];
            foreach (ComparisonMetric::cases() as $metric) {
                $values[$metric->value] = $comparedActivity->getValueFor($metric, $this->unitSystem);
            }

            $rows[] = [
                'id' => (string) $comparedActivity->getActivityId(),
                'label' => $comparedActivity->getName(),
                'date' => $comparedActivity->getStartDateTime()->format('Y-m-d H:i:s'),
                'values' => $values,
            ];
        }

        return [
            'metrics' => $metrics,
            'defaults' => [
                'primary' => $this->firstAvailable(self::PREFERRED_PRIMARY, $available),
                'secondary' => $this->firstAvailable(self::PREFERRED_SECONDARY, $available),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param ComparisonMetric[]  $preferred
     * @param array<string, bool> $available
     */
    private function firstAvailable(array $preferred, array $available): ?string
    {
        foreach ($preferred as $metric) {
            if ($available[$metric->value]) {
                return $metric->value;
            }
        }

        return null;
    }
}
