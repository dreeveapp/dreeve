<?php

declare(strict_types=1);

namespace App\Infrastructure\Tokenizer;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TokenDefinition implements TranslatableInterface
{
    private function __construct(
        private string $prefix,
        private string $key,
        /** @var \Closure(TranslatorInterface, ?string): string */
        private \Closure $description,
        private bool $supportsModifier,
        private ?string $exampleModifier,
    ) {
    }

    /**
     * @param \Closure(TranslatorInterface, ?string): string $description
     */
    public static function create(
        string $prefix,
        string $key,
        \Closure $description,
        bool $supportsModifier = false,
        ?string $exampleModifier = null,
    ): self {
        return new self(
            prefix: $prefix,
            key: $key,
            description: $description,
            supportsModifier: $supportsModifier,
            exampleModifier: $exampleModifier,
        );
    }

    public function matches(Token $token): bool
    {
        if ($token->getPrefix() !== $this->prefix || $token->getKey() !== $this->key) {
            return false;
        }
        if (null !== $token->getModifier() && !$this->supportsModifier) {
            return false;
        }

        return true;
    }

    public function getTokenString(): string
    {
        return sprintf('[%s:%s]', $this->prefix, $this->key);
    }

    public function getExampleTokenString(): string
    {
        if (null === $this->exampleModifier) {
            return $this->getTokenString();
        }

        return sprintf('[%s:%s:%s]', $this->prefix, $this->key, $this->exampleModifier);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return ($this->description)($translator, $locale);
    }
}
