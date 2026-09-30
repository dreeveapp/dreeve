<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear;

use App\Domain\Gear\AddGear\AddGear;
use App\Domain\Gear\DeleteGear\DeleteGear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearStatus;
use App\Domain\Gear\GearUsage;
use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearRepository $gearRepository,
        private GearUsage $gearUsage,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/gear/add', name: 'admin_add_gear', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/edit-gear.html.twig', [
            'dispatchCommand' => AddGear::getCommandName(),
            'statuses' => GearStatus::cases(),
            'isDeletable' => false,
        ]));
    }

    #[Route(path: '/admin/gear/{gearId}/edit', name: 'admin_edit_gear', methods: ['GET'], priority: 10)]
    public function handleEdit(string $gearId): HtmlResponse
    {
        $gear = $this->gearRepository->find(GearId::fromString($gearId));

        return new HtmlResponse($this->twig->render('html/admin/page/gear/edit-gear.html.twig', [
            'dispatchCommand' => UpdateGear::getCommandName(),
            'gear' => $gear,
            'statuses' => GearStatus::cases(),
            'isDeletable' => $gear->isDeletableIn($this->importMode),
        ]));
    }

    #[Route(path: '/admin/gear/{gearId}/delete', name: 'admin_delete_gear', methods: ['GET'], priority: 10)]
    public function handleDelete(string $gearId): HtmlResponse
    {
        $gear = $this->gearRepository->find(GearId::fromString($gearId));
        if (!$gear->isDeletableIn($this->importMode)) {
            throw new NotFoundHttpException('Gear cannot be deleted');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/gear/delete-gear.html.twig', [
            'dispatchCommand' => DeleteGear::getCommandName(),
            'gear' => $gear,
            'isInUse' => $this->gearUsage->isInUse($gear),
        ]));
    }
}
