<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Measurement\UnitSystem;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class CombinedActivityStream
{
    /** @var array<string, array<int, float>> */
    private array $chartStreamDataCache = [];

    /**
     * @param array<mixed> $data
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private readonly ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private readonly UnitSystem $unitSystem,
        #[ORM\Column(type: 'string')]
        private readonly CombinedStreamTypes $streamTypes,
        #[ORM\Column(type: 'blob')]
        private readonly array $data,
        #[ORM\Column(type: 'integer')]
        private readonly int $maxYAxisValue,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function create(
        ActivityId $activityId,
        UnitSystem $unitSystem,
        CombinedStreamTypes $streamTypes,
        array $data,
        int $maxYAxisValue,
    ): self {
        return new self(
            activityId: $activityId,
            unitSystem: $unitSystem,
            streamTypes: $streamTypes,
            data: $data,
            maxYAxisValue: $maxYAxisValue,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromState(
        ActivityId $activityId,
        UnitSystem $unitSystem,
        CombinedStreamTypes $streamTypes,
        array $data,
        int $maxYAxisValue,
    ): self {
        return new self(
            activityId: $activityId,
            unitSystem: $unitSystem,
            streamTypes: $streamTypes,
            data: $data,
            maxYAxisValue: $maxYAxisValue,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getUnitSystem(): UnitSystem
    {
        return $this->unitSystem;
    }

    public function getStreamTypes(): CombinedStreamTypes
    {
        return $this->streamTypes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getMaxYAxisValue(): int
    {
        return $this->maxYAxisValue;
    }

    public function getMaximumNumberOfDigits(): int
    {
        return strlen((string) $this->maxYAxisValue);
    }

    /**
     * @return array<int, float>
     */
    public function getDistances(): array
    {
        return $this->getChartStreamData(CombinedStreamType::DISTANCE);
    }

    /**
     * @return array<int, float>
     */
    public function getTimes(): array
    {
        return $this->getChartStreamData(CombinedStreamType::TIME);
    }

    /**
     * @return array<int, float>
     */
    public function getGrades(): array
    {
        return $this->getChartStreamData(CombinedStreamType::GRADE);
    }

    /**
     * @return array<int, array<float, float>>
     */
    public function getCoordinates(): array
    {
        $coordinateIndex = array_search(CombinedStreamType::LAT_LNG, $this->streamTypes->toArray(), true);
        if (false === $coordinateIndex) {
            return [];
        }

        return array_values(array_filter(array_column($this->data, $coordinateIndex)));
    }

    public function getStreamTypesForCharts(): CombinedStreamTypes
    {
        $this->buildChartStreamDataCache();
        $streamTypesForCharts = CombinedStreamTypes::empty();

        foreach (array_keys($this->chartStreamDataCache) as $streamType) {
            if (!CombinedStreamType::from($streamType)->isChartable()) {
                continue;
            }
            $streamTypesForCharts->add(CombinedStreamType::from($streamType));
        }

        return $streamTypesForCharts;
    }

    /**
     * @return array<int, float>
     */
    public function getChartStreamData(CombinedStreamType $streamType): array
    {
        $this->buildChartStreamDataCache();

        return $this->chartStreamDataCache[$streamType->value] ?? [];
    }

    private function buildChartStreamDataCache(): void
    {
        if ([] !== $this->chartStreamDataCache) {
            // Cache has been built already.
            return;
        }

        $streamTypes = $this->streamTypes->toArray();

        foreach ($this->streamTypes as $streamType) {
            $index = array_search($streamType, $streamTypes, true);
            if (false === $index) {
                continue;
            }

            if (!$data = array_column($this->data, $index)) {
                continue;
            }

            $this->chartStreamDataCache[$streamType->value] = $data;
        }
    }
}
