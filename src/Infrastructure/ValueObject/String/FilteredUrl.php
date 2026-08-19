<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

use App\Application\AppUrl;

final readonly class FilteredUrl
{
    private const array RANGE_BOUNDS = ['from', 'to'];

    private function __construct(
        private RelativeUrl $relativeUrl,
        /** @var array<string, mixed> */
        private array $filters,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function from(string $path, array $filters, AppUrl $appUrl): self
    {
        return new self(
            relativeUrl: RelativeUrl::from($path, $appUrl),
            filters: $filters,
        );
    }

    public function toRelativeUrl(): string
    {
        $queryString = implode('&', $this->toQueryParams());

        return $this->relativeUrl->toRelativeUrl().('' === $queryString ? '' : '?'.$queryString);
    }

    /**
     * @return string[]
     */
    private function toQueryParams(): array
    {
        $params = [];

        foreach ($this->filters as $name => $value) {
            if ($value instanceof \Traversable) {
                $value = iterator_to_array($value);
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    $params[] = sprintf('filters[%s][]=%s', $name, $this->encode($item));
                }
                continue;
            }

            if (is_array($value)) {
                foreach (self::RANGE_BOUNDS as $bound) {
                    if (!isset($value[$bound])) {
                        continue;
                    }
                    $params[] = sprintf('filters[%s][%s]=%s', $name, $bound, $this->encode($value[$bound]));
                }
                continue;
            }

            $params[] = sprintf('filters[%s]=%s', $name, $this->encode($value));
        }

        return $params;
    }

    private function encode(mixed $value): string
    {
        return rawurlencode($value instanceof \BackedEnum ? (string) $value->value : (string) $value);
    }
}
