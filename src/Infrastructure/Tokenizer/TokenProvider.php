<?php

declare(strict_types=1);

namespace App\Infrastructure\Tokenizer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.tokenizer.token_provider')]
interface TokenProvider
{
    public function getPrefix(): string;

    /**
     * @return TokenDefinition[]
     */
    public function getTokenDefinitions(): array;

    public function resolve(Token $token, TokenizerContext $context): ?string;
}
