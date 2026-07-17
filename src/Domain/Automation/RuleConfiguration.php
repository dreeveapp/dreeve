<?php

declare(strict_types=1);

namespace App\Domain\Automation;

final class RuleConfiguration implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $configuration
     */
    private function __construct(
        private array $configuration = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self($config);
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

    public function getString(string $key): string
    {
        if (!array_key_exists($key, $this->configuration)) {
            throw new \InvalidArgumentException(sprintf('Configuration value %s is missing', $key));
        }
        if (!is_string($this->configuration[$key])) {
            throw new \InvalidArgumentException(sprintf('Configuration value %s is not a string', $key));
        }

        return $this->configuration[$key];
    }

    public function getNumber(string $key): int|float
    {
        if (!array_key_exists($key, $this->configuration)) {
            throw new \InvalidArgumentException(sprintf('Configuration value %s is missing', $key));
        }
        if (!is_int($this->configuration[$key]) && !is_float($this->configuration[$key])) {
            throw new \InvalidArgumentException(sprintf('Configuration value %s is not a number', $key));
        }

        return $this->configuration[$key];
    }

    /**
     * @return array<int, mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->configuration[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Configuration value %s is not an array', $key));
        }

        return array_values($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
