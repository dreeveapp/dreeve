<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

final readonly class RouteGeography implements \JsonSerializable
{
    public const string IS_REVERSE_GEOCODED = 'is_reverse_geocoded';
    public const string PASSED_TROUGH_COUNTRIES = 'passed_through_countries';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function getStartingPointCountryCode(): ?string
    {
        return $this->data['country_code'] ?? null;
    }

    public function getStartingPointCity(): ?string
    {
        return $this->data['city'] ?? $this->data['town'] ?? $this->data['village'] ?? $this->data['municipality'] ?? null;
    }

    public function getStartingPointState(): ?string
    {
        return $this->data['state'] ?? $this->data['county'] ?? null;
    }

    public function isReversedGeocoded(): bool
    {
        return (bool) ($this->data[self::IS_REVERSE_GEOCODED] ?? false);
    }

    public function hasBeenAnalyzedForRouteGeography(): bool
    {
        return array_key_exists(self::PASSED_TROUGH_COUNTRIES, $this->data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateWith(array $data): self
    {
        return self::create(array_replace_recursive($this->data, $data));
    }

    /**
     * @return string[]
     */
    public function getPassedThroughCountries(): array
    {
        return array_values(array_unique(array_map(
            strtolower(...),
            array_filter([$this->getStartingPointCountryCode() ?? null, ...$this->data[self::PASSED_TROUGH_COUNTRIES] ?? []])
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
