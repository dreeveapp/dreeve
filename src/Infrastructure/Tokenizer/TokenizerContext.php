<?php

declare(strict_types=1);

namespace App\Infrastructure\Tokenizer;

final readonly class TokenizerContext
{
    private function __construct(
        /** @var array<class-string, object> */
        private array $objects,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function with(object $object): self
    {
        return new self([
            ...$this->objects,
            $object::class => $object,
        ]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function get(string $className): ?object
    {
        $object = $this->objects[$className] ?? null;
        if (null !== $object && !$object instanceof $className) {
            return null;
        }

        return $object;
    }
}
