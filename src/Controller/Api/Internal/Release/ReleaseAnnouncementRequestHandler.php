<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Release;

use App\Infrastructure\Http\Response\CachePolicy;
use App\Infrastructure\Http\Response\HtmlResponse;
use App\Infrastructure\Security\Visitor;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ReleaseAnnouncementRequestHandler
{
    public function __construct(
        private Visitor $visitor,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/release-announcement', name: 'release_announcement', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        if (!$this->visitor->isAuthenticated()) {
            return CachePolicy::private()->noStore()->applyTo(new HtmlResponse(''));
        }

        return CachePolicy::private()->noStore()->applyTo(
            new HtmlResponse($this->twig->load('html/component/release-announcement.html.twig')->render()),
        );
    }
}
