<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Collection;

use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\AddActivityCollection\AddActivityCollection;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageActivityCollectionFormRequestHandler
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/admin/collections/add', name: 'admin_add_activity_collection', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/activity-collection/edit-activity-collection.html.twig', [
            'dispatchCommand' => AddActivityCollection::getCommandName(),
            'activityCollectionId' => ActivityCollectionId::random(),
            'types' => ActivityCollectionType::cases(),
        ]));
    }
}
