<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\AppStatusChecker;
use App\Application\IndexPage;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AppRequestHandler
{
    public function __construct(
        private AppStatusChecker $appStatusChecker,
        private IndexPage $indexPage,
        private FragmentRenderer $fragmentRenderer,
    ) {
    }

    #[Route(path: '/{wildcard?}', name: 'app', requirements: ['wildcard' => '.*'], methods: ['GET'], priority: -10)]
    public function handle(): Response
    {
        if (!$this->appStatusChecker->hasBeenBuilt()) {
            throw new NotFoundHttpException('Not found');
        }

        return $this->fragmentRenderer->render($this->indexPage);
    }
}
