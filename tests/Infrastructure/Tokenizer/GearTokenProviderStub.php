<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Tokenizer;

use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Infrastructure\Tokenizer\TokenProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GearTokenProviderStub implements TokenProvider
{
    public function getPrefix(): string
    {
        return 'gear';
    }

    public function getTokenDefinitions(): array
    {
        return [
            TokenDefinition::create(
                prefix: 'gear',
                key: 'name',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => 'The gear name',
            ),
        ];
    }

    public function resolve(Token $token, TokenizerContext $context): ?string
    {
        return $context->get(GearStub::class)?->getName();
    }
}
