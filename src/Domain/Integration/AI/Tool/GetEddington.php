<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Eddington\Eddington;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class GetEddington implements AITool
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
    ) {
    }

    public function getName(): string
    {
        return 'get_eddington';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s Eddington numbers per configured sport group, in both kilometers and miles.
            An Eddington number E means the athlete covered at least E distance units on at least E different days.
            The result also includes how many more days are needed to reach the next number.
            Use this tool when the user asks about their Eddington number or long-term distance consistency.
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        return array_map(
            static fn (Eddington $eddington): array => [
                'label' => $eddington->getLabel(),
                'sportTypes' => $eddington->getConfig()->getSportTypesToInclude()->map(
                    static fn (SportType $sportType): string => $sportType->value
                ),
                'unit' => $eddington->getUnitSystem()->distanceSymbol(),
                'number' => $eddington->getNumber(),
                'daysNeededForNextNumber' => $eddington->getDaysToCompleteForFutureNumbers()[$eddington->getNumber() + 1] ?? $eddington->getNumber() + 1,
                'longestDistanceInADay' => $eddington->getLongestDistanceInADay(),
            ],
            $this->eddingtonCalculator->calculate(...UnitSystem::cases()),
        );
    }
}
