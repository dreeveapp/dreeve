<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\IndexPage;
use App\Domain\Activity\ActivityIdRepository;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Infrastructure\Http\Fragment\FragmentType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AppRequestHandler
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private IndexPage $indexPage,
        private FragmentRegistry $fragmentRegistry,
        private FragmentRenderer $fragmentRenderer,
    ) {
    }

    #[Route(path: '/{wildcard?}', name: 'app', requirements: ['wildcard' => '.*'], methods: ['GET'], priority: -10)]
    public function handle(?string $wildcard = null): Response
    {
        if ($this->activityIdRepository->count() <= 0) {
            throw new NotFoundHttpException('Not found');
        }

        $path = trim($wildcard ?? '', '/');
        $pageExists = '' === $path || $this->fragmentRegistry->findOfType($path, FragmentType::PAGE) instanceof \App\Infrastructure\Http\Fragment\Fragment;

        $response = $this->fragmentRenderer->render($this->indexPage);
        $response->setStatusCode($pageExists ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);

        return $response;
    }
}
