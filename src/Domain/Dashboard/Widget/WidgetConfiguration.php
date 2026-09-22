<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

final class WidgetConfiguration
{
    public const string TITLE = 'title';
    public const int MAX_TITLE_LENGTH = 255;

    /** @var array<string, mixed> */
    private array $configuration = [];

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param int|string|float|bool|array<int, int|string|mixed>|null $value
     */
    public function add(string $key, int|string|float|bool|array|null $value): self
    {
        $this->configuration[$key] = $value;

        return $this;
    }

    /**
     * @return int|string|float|bool|array<int, int|string|mixed>|null $value
     */
    public function get(string $key, mixed $default = null): int|string|float|bool|array|null
    {
        return $this->configuration[$key] ?? $default;
    }

    public function getTitle(): ?string
    {
        $title = $this->get(self::TITLE);

        return is_string($title) ? $title : null;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->configuration);
    }

    public function isEmpty(): bool
    {
        return [] === $this->configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->configuration;
    }
}
