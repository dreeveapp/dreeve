<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Integration\AI\Chat\SafeMarkdownRenderer;
use Twig\Attribute\AsTwigFilter;

final readonly class MarkdownTwigExtension
{
    public function __construct(
        private SafeMarkdownRenderer $safeMarkdownRenderer,
    ) {
    }

    #[AsTwigFilter('chat_markdown', isSafe: ['html'])]
    public function chatMarkdown(string $markdown): string
    {
        return $this->safeMarkdownRenderer->render($markdown);
    }
}
