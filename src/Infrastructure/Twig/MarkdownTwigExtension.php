<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Renderer\MarkdownRenderer;
use Twig\Attribute\AsTwigFilter;

final readonly class MarkdownTwigExtension
{
    public function __construct(
        private MarkdownRenderer $markdownRenderer,
    ) {
    }

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function markdown(string $markdown): string
    {
        return $this->markdownRenderer->render($markdown);
    }
}
