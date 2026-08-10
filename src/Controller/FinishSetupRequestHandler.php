<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Activity\ActivityIdRepository;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class FinishSetupRequestHandler
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/finish-setup', name: 'finish_setup', methods: ['GET'], priority: 2)]
    public function handle(): Response
    {
        if ($this->activityIdRepository->count() > 0) {
            // The app is ready, load it.
            return new RedirectResponse('/', Response::HTTP_FOUND);
        }

        return new HtmlResponse($this->twig->render('html/finish-setup.html.twig'));
    }
}
