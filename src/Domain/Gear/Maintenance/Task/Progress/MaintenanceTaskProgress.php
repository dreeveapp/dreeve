<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

final readonly class MaintenanceTaskProgress
{
    private function __construct(
        private float $elapsed,
        private float $interval,
        private string $elapsedDescription,
        private string $remainingDescription,
    ) {
        if ($this->interval <= 0) {
            throw new \InvalidArgumentException('Interval must be greater than 0');
        }
    }

    public static function from(
        float $elapsed,
        float $interval,
        string $elapsedDescription,
        string $remainingDescription,
    ): self {
        return new self(
            elapsed: $elapsed,
            interval: $interval,
            elapsedDescription: $elapsedDescription,
            remainingDescription: $remainingDescription,
        );
    }

    public function getPercentage(): int
    {
        return min((int) round($this->getCompletionRatio() * 100), 100);
    }

    public function getCompletionRatio(): float
    {
        return $this->elapsed / $this->interval;
    }

    public function getRemaining(): float
    {
        return $this->interval - $this->elapsed;
    }

    public function getDescription(): string
    {
        return $this->elapsedDescription;
    }

    public function getRemainingDescription(): string
    {
        return $this->remainingDescription;
    }

    public function isOverdue(): bool
    {
        return $this->getRemaining() < 0;
    }

    public function isDue(): bool
    {
        return $this->getPercentage() >= 98;
    }

    public function isZero(): bool
    {
        return 0 === $this->getPercentage();
    }

    public function isLow(): bool
    {
        return $this->getPercentage() < 70;
    }

    public function isMedium(): bool
    {
        return $this->getPercentage() >= 70 && $this->getPercentage() < 90;
    }

    public function isHigh(): bool
    {
        return $this->getPercentage() >= 90;
    }
}
