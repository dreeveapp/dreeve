<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\IndexPage;
use App\Application\Navigation\HasNavigationSection;
use App\Application\NotFoundFragment;
use App\Domain\Activity\ActivityIdRepository;
use App\Infrastructure\Http\Fragment\Fragment;
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
    private const string DEFAULT_PAGE_PATH = 'dashboard';

    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private IndexPage $indexPage,
        private FragmentRegistry $fragmentRegistry,
        private FragmentRenderer $fragmentRenderer,
        private NotFoundFragment $notFoundFragment,
    ) {
    }

    #[Route(path: '/{wildcard?}', name: 'app', requirements: ['wildcard' => '.*'], methods: ['GET'], priority: -10)]
    public function handle(?string $wildcard = null): Response
    {
        if ($this->activityIdRepository->count() <= 0) {
            throw new NotFoundHttpException('Not found');
        }

        $path = trim($wildcard ?? '', '/') ?: self::DEFAULT_PAGE_PATH;
        $page = $this->fragmentRegistry->findOfType($path, FragmentType::PAGE);

        $response = $this->fragmentRenderer->render($page ?? $this->notFoundFragment);
        $response->setContent(str_replace(
            IndexPage::MAIN_CONTENT_MARKER,
            (string) $response->getContent(),
            (string) $this->fragmentRenderer->render($this->indexPage->forSection(
                $page instanceof HasNavigationSection ? $page->getNavigationSection() : null,
            ))->getContent(),
        ));
        $response->setStatusCode($page instanceof Fragment ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);

        return $response;
    }
}
