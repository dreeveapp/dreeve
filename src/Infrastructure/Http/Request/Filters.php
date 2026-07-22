<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request;

use Symfony\Component\HttpFoundation\Request;

abstract readonly class Filters
{
    /**
     * @param array<array-key, mixed> $filters
     */
    final protected function __construct(
        private array $filters,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static($request->query->all('filters'));
    }

    abstract public function isEmpty(): bool;

    protected function getString(string $name): ?string
    {
        $value = $this->filters[$name] ?? null;
        if (!is_string($value) || '' === $value) {
            return null;
        }

        return $value;
    }
}
