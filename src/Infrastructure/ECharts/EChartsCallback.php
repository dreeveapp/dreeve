<?php

declare(strict_types=1);

namespace App\Infrastructure\ECharts;

final readonly class EChartsCallback implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $args
     */
    private function __construct(
        private string $name,
        private array $args = [],
    ) {
    }

    public static function formatSeconds(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatSecondsTrimZero(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatDuration(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatHours(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatWholeNumberWithSymbol(string $symbol): self
    {
        return new self(__FUNCTION__, ['symbol' => $symbol]);
    }

    public static function formatPercentage(): self
    {
        return new self(__FUNCTION__);
    }

    public static function toInteger(): self
    {
        return new self(__FUNCTION__);
    }

    public static function symbolSize(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatRounded(int $decimals, ?string $suffix = null): self
    {
        return new self(__FUNCTION__, ['decimals' => $decimals, 'suffix' => $suffix]);
    }

    public static function formatCombinedProfileTooltip(string $paceSymbol): self
    {
        return new self(__FUNCTION__, ['paceSymbol' => $paceSymbol]);
    }

    public static function formatDataTooltip(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatPowerDurationTooltip(): self
    {
        return new self(__FUNCTION__);
    }

    public static function formatStackedDistanceTooltip(string $symbol): self
    {
        return new self(__FUNCTION__, ['symbol' => $symbol]);
    }

    public static function formatDateOnlyTooltip(): self
    {
        return new self(__FUNCTION__);
    }

    /**
     * @return array{callback: string, args?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        if ([] === $this->args) {
            return ['callback' => $this->name];
        }

        return ['callback' => $this->name, 'args' => $this->args];
    }
}
