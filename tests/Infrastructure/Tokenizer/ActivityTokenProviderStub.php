<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Tokenizer;

use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Infrastructure\Tokenizer\TokenProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityTokenProviderStub implements TokenProvider
{
    public function getPrefix(): string
    {
        return 'activity';
    }

    public function getTokenDefinitions(): array
    {
        return [
            TokenDefinition::create(
                prefix: 'activity',
                key: 'name',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => 'The name',
            ),
            TokenDefinition::create(
                prefix: 'activity',
                key: 'start-date',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => 'The start date',
                supportsModifier: true,
                exampleModifier: 'd-m-Y',
            ),
        ];
    }

    public function resolve(Token $token, TokenizerContext $context): ?string
    {
        return match ($token->getKey()) {
            'name' => 'Morning Ride',
            'start-date' => $token->getModifier() ?? 'default-format',
            default => null,
        };
    }
}
