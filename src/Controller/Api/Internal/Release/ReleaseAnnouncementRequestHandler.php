<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Release;

use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
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
    public function handle(): PrivateNoStoreHtmlResponse
    {
        if (!$this->visitor->isAuthenticated()) {
            return new PrivateNoStoreHtmlResponse('');
        }

        return new PrivateNoStoreHtmlResponse($this->twig->load('html/component/release-announcement.html.twig')->render());
    }
}
