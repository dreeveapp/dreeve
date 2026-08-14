<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\SportType\SportType;

final readonly class RouteSummary
{
    /**
     * @param SportType[] $sportTypes
     * @param string[]    $countries
     */
    private function __construct(
        private int $numberOfRoutes,
        private array $sportTypes,
        private array $countries,
    ) {
    }

    /**
     * @param SportType[] $sportTypes
     * @param string[]    $countries
     */
    public static function create(int $numberOfRoutes, array $sportTypes, array $countries): self
    {
        return new self(
            numberOfRoutes: $numberOfRoutes,
            sportTypes: array_values(array_filter(
                SportType::cases(),
                static fn (SportType $sportType): bool => in_array($sportType, $sportTypes, true)
            )),
            countries: array_values(array_unique($countries)),
        );
    }

    public function getNumberOfRoutes(): int
    {
        return $this->numberOfRoutes;
    }

    /**
     * @return SportType[]
     */
    public function getSportTypes(): array
    {
        return $this->sportTypes;
    }

    /**
     * @return string[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    public function getNumberOfCountries(): int
    {
        return count($this->getCountries());
    }
}
