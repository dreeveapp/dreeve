<?php

declare(strict_types=1);

namespace App\Infrastructure\Tokenizer;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Tokenizer
{
    /** @var array<string, TokenProvider> */
    private array $providers;

    /**
     * @param iterable<TokenProvider> $providers
     */
    public function __construct(
        #[AutowireIterator('app.tokenizer.token_provider')]
        iterable $providers,
    ) {
        $providersByPrefix = [];
        foreach ($providers as $provider) {
            $prefix = $provider->getPrefix();
            if (array_key_exists($prefix, $providersByPrefix)) {
                throw new \InvalidArgumentException(sprintf('Duplicate token provider for prefix "%s".', $prefix));
            }
            $providersByPrefix[$prefix] = $provider;
        }
        $this->providers = $providersByPrefix;
    }

    public function replace(string $text, TokenizerContext $context): string
    {
        if ([] === $this->providers) {
            return $text;
        }

        return preg_replace_callback(
            $this->buildPattern(),
            function (array $matches) use ($context): string {
                $token = $this->buildToken($matches);
                if (!$this->hasMatchingDefinition($token)) {
                    return $token->getRaw();
                }

                return $this->providers[$token->getPrefix()]->resolve($token, $context) ?? $token->getRaw();
            },
            $text
        ) ?? $text;
    }

    /**
     * @return string[]
     */
    public function findInvalidTokens(string $text): array
    {
        if ([] === $this->providers) {
            return [];
        }

        preg_match_all($this->buildPattern(), $text, $matches, PREG_SET_ORDER);

        $invalidTokens = [];
        foreach ($matches as $match) {
            $token = $this->buildToken($match);
            if ($this->hasMatchingDefinition($token)) {
                continue;
            }
            $invalidTokens[] = $token->getRaw();
        }

        return $invalidTokens;
    }

    /**
     * @return TokenDefinition[]
     */
    public function getTokenDefinitions(): array
    {
        $definitions = [];
        foreach ($this->providers as $provider) {
            $definitions = [...$definitions, ...$provider->getTokenDefinitions()];
        }

        return $definitions;
    }

    private function hasMatchingDefinition(Token $token): bool
    {
        foreach ($this->providers[$token->getPrefix()]->getTokenDefinitions() as $definition) {
            if ($definition->matches($token)) {
                return true;
            }
        }

        return false;
    }

    private function buildPattern(): string
    {
        return sprintf(
            '/\[(%s):([a-z0-9-]+)(?::([^\[\]]+))?\]/',
            implode('|', array_map(
                static fn (string $prefix): string => preg_quote($prefix, '/'),
                array_keys($this->providers)
            ))
        );
    }

    /**
     * @param array<int|string, string> $matches
     */
    private function buildToken(array $matches): Token
    {
        return Token::create(
            prefix: $matches[1],
            key: $matches[2],
            modifier: $matches[3] ?? null,
            raw: $matches[0],
        );
    }
}
