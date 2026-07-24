<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\Tokenizer;
use Twig\Attribute\AsTwigFunction;

final readonly class TokenizerTwigExtension
{
    public function __construct(
        private Tokenizer $tokenizer,
    ) {
    }

    /**
     * @return TokenDefinition[]
     */
    #[AsTwigFunction('available_tokens')]
    public function getAvailableTokens(): array
    {
        return $this->tokenizer->getTokenDefinitions();
    }
}
